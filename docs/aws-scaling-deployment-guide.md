# AWS scaling and deployment decision guide

This guide records the current ShopNXE deployment recommendation, reversible
application controls, decisions that can be prepared in source control, and
decisions that require an AWS account owner. It is a planning baseline, not a
claim that a particular request rate has already been demonstrated.

## 1. Capacity language

Store count is not the same as concurrency. Five thousand registered Stores
with a few hundred active users is different from five thousand Stores each
generating requests every second. Capacity decisions must state:

- registered and simultaneously active Stores;
- concurrent users and peak requests per second;
- read/write and endpoint mix;
- Product/catalog size distribution;
- p95 and p99 latency objectives; and
- background-job and media volume.

The repository has strong Store isolation and an Octane-compatible modular
monolith, but AWS capacity remains unproven until the read-only k6 harness in
`load-tests/` runs against an instrumented staging deployment.

## 2. Recommended production shape

```text
Route 53
   |
CloudFront + AWS WAF
   |
Application Load Balancer
   |
ECS/Fargate Octane web service (minimum two tasks, multiple AZs)
   |---------------- RDS Proxy -------- RDS PostgreSQL Multi-AZ
   |---------------- ElastiCache Redis/Valkey Multi-AZ
   |---------------- S3 private media bucket
   `---------------- optional managed search

ElastiCache queues ---- ECS/Fargate Horizon worker services
                       |- critical/default workers
                       `- isolated media/export workers
```

Run web, Horizon, and Reverb as separate ECS services with the same immutable
image and different commands. Do not run migrations in every web task; use one
controlled release task. Keep database, Redis, and workers in private subnets.
Only the load balancer should accept public web traffic.

ECS target tracking can scale a service using CPU, memory, or ALB request count
per target. RDS Proxy pools application connections and protects PostgreSQL
from a sudden increase in Octane and Horizon workers. Multi-AZ is an
availability control; it does not replace query optimization or load testing.

Official references:

- [ECS target-tracking scaling](https://docs.aws.amazon.com/AmazonECS/latest/developerguide/target-tracking-create-policy.html)
- [RDS Proxy](https://docs.aws.amazon.com/AmazonRDS/latest/UserGuide/rds-proxy.html)
- [RDS Multi-AZ](https://docs.aws.amazon.com/AmazonRDS/latest/UserGuide/Concepts.MultiAZ.html)
- [ElastiCache Multi-AZ and automatic failover](https://docs.aws.amazon.com/AmazonElastiCache/latest/dg/AutoFailover.html)
- [AWS WAF rate-based rules](https://docs.aws.amazon.com/waf/latest/developerguide/waf-rule-statement-type-rate-based.html)

## 3. Starting sizes

These are safe starting points for measurement, not permanent commitments or
throughput guarantees. Confirm regional availability and current pricing in
the AWS Pricing Calculator before approval.

### Recommended: ECS on Fargate

| Workload | Starting allocation | Minimum | Scaling signal |
| --- | --- | --- | --- |
| Octane web | 2 vCPU, 4 GiB per task | 2 tasks across AZs | ALB requests/target plus CPU |
| Critical/default Horizon | 1 vCPU, 2 GiB per task | 2 production tasks | queue age plus CPU |
| Media/export Horizon | 2 vCPU, 4 GiB per task | 1 task; 2 when continuously required | queue age plus memory |
| Reverb | 1 vCPU, 2 GiB per task | 2 when realtime is critical | connections plus CPU |

Fargate is the preferred first production choice because task CPU/memory and
service scaling are explicit and no EC2 fleet must be patched. AWS supports
the recommended combinations; see
[Fargate task sizes](https://docs.aws.amazon.com/AmazonECS/latest/developerguide/task-cpu-memory-error.html).

### PostgreSQL

Start production validation with:

- RDS PostgreSQL `db.r7g.large` (2 vCPU, 16 GiB) in Multi-AZ;
- RDS Proxy between all ShopNXE services and the writer;
- encrypted GP3 storage with storage autoscaling and a deliberate maximum;
- automated backups, deletion protection, Performance Insights, and slow-query
  monitoring; and
- a reader endpoint only after replica-lag behavior is tested.

Use `db.r7g.xlarge` (4 vCPU, 32 GiB) when measured working set, CPU, or
connection workload does not fit the baseline. AWS publishes those
specifications in
[RDS instance classes](https://docs.aws.amazon.com/AmazonRDS/latest/UserGuide/Concepts.DBInstanceClass.Summary.html).
Do not begin with a burstable database class for sustained multi-Store
production; it can be acceptable for staging or a low-traffic pilot.

### Redis/Valkey

For production queues, cache, sessions, and rate limits, start with one
`cache.r7g.large` primary and one replica in another AZ. AWS currently lists
approximately 13 GiB for this type in
[supported ElastiCache node types](https://docs.aws.amazon.com/AmazonElastiCache/latest/dg/CacheNodes.SupportedTypes.html).
Staging may use a smaller burstable node, but queue and session durability must
not depend on a single production node.

### EC2 alternative

If predictable always-on usage makes EC2 preferable, begin with at least two
`c7i.xlarge` instances (4 vCPU, 8 GiB each) in an Auto Scaling group across
Availability Zones. Keep Horizon on a separate service/fleet. AWS publishes
the current sizes at [Amazon EC2 C7i](https://aws.amazon.com/ec2/instance-types/c7i/).
This alternative requires OS patching, capacity-provider management, bin
packing, and deployment-drain ownership, so it is not the first recommendation.

## 4. Reversible application flags

All new behavior-changing flags default to `false`. Enable one change at a
time in staging, rebuild Laravel configuration, reload Octane, and observe at
least one normal peak window before proceeding.

| Flag | Effect | Suggested rollout | Rollback |
| --- | --- | --- | --- |
| `SCALABILITY_REQUEST_PERFORMANCE_ENABLED` | Samples route duration, query count/time, memory and Store ID | First; start at `0.05` | Set false and reload |
| `SCALABILITY_SERVER_TIMING_HEADER_ENABLED` | Adds application duration to `Server-Timing` for k6 correlation | Staging initially | Set false |
| `SCALABILITY_STORE_LOOKUP_CACHE_ENABLED` | Caches non-authoritative Store attributes for header resolution | After Redis is Multi-AZ | Set false; TTL expires keys |
| `SCALABILITY_PRODUCT_DETAIL_REFERENCE_CACHE_ENABLED` | Caches rendered Store reference catalogs with generation invalidation | After timing baseline | Set false; data becomes unused |
| `SCALABILITY_STORE_PRODUCT_RATE_LIMIT_ENABLED` | Adds per-Store/per-user Product API read/write limits | Start generously, then tune | Set false |
| `DB_READ_WRITE_SPLIT_ENABLED` | Routes eligible reads to `DB_READ_HOSTS` with sticky writes | Only after lag testing | Set false; use writer |
| `OCTANE_DISCONNECT_DATABASES` | Disconnects database handles after each operation | Only if proxy/connection evidence requires it | Set false |
| `OCTANE_COLLECT_GARBAGE` | Forces operation-end garbage collection | Only after memory profiling | Set false |

Authorization is intentionally not cached: every request still validates the
active Store membership and token Store binding against PostgreSQL. Cache
failures fall back to the existing database path. Store and Product reference
cache invalidation occurs after successful transactions, with TTL as a safety
net.

Recommended enablement order:

1. performance logging and staging-only `Server-Timing`;
2. Store lookup cache;
3. Product Detail reference cache;
4. Product API rate limits;
5. reader routing only when an RDS reader is available; and
6. Octane disconnect/garbage flags only from measured evidence.

### Rollback mechanics

On ECS, set the affected flag to `false` in a new task-definition revision and
redeploy the same known-good image. This rolls back behavior without mixing a
code rollback into the incident. On an EC2/supervisor deployment, update the
secret/environment source, rebuild Laravel's configuration cache, run
`php artisan octane:reload`, and run `php artisan horizon:terminate` when worker
configuration changed. Do not flush all shared Redis data: disabled cache keys
become unreachable and expire naturally. Roll back the database split before
removing or failing over a configured reader endpoint.

## 5. Load-test procedure and gates

The repository's k6 harness is read-only. Populate an untracked
`load-tests/stores.local.json` with staging tokens, then ramp gradually. Run
the selective Product Detail path first and repeat with
`WITH_REFERENCE_DATA=true`.

Suggested initial acceptance gates:

- zero cross-Store responses and authorization bypasses;
- less than 1% failed requests during the target window;
- p95 below 1 second and p99 below 2 seconds for the measured Product Detail
  mix, then tighten per product requirements;
- database and Redis connection/memory headroom above 30%;
- no sustained queue-age growth after traffic returns to normal; and
- no Product corruption or unexpected `409`/deadlock increase.

Concurrent write tests require a disposable staging database because they
change Product revisions. They must never target production Store data.

## 6. Monitoring and alarms

Before load testing, create dashboards and alarms for:

- ALB request count, target response time, 4xx/5xx, and unhealthy targets;
- ECS desired/running tasks, CPU, memory, restarts, and deployment health;
- RDS CPU, free memory/storage, connections, proxy borrow latency, locks,
  deadlocks, replica lag, and failover events;
- Redis memory, evictions, connections, latency, replication, and failover;
- Horizon queue depth, oldest-job age, failures, retries, and runtime; and
- application p50/p95/p99 by route plus cache hit/miss ratios.

Use request IDs to correlate ALB, application, queue, and database observations.
Avoid Store public IDs and user input as unbounded metric dimensions; they
belong in access-controlled structured logs.

## 7. Decisions requiring the owner

The following cannot be selected or applied safely from source code alone:

- AWS account, region, availability-zone, and data-residency choices;
- monthly budget and reserved/Savings Plan commitments;
- domain, Route 53 zone, TLS certificate, and WAF policy;
- VPC/subnet strategy and connectivity to existing systems;
- production secrets and KMS ownership;
- RDS retention, recovery-point, and recovery-time objectives;
- a maintenance window and production load-test authorization; and
- final instance/task sizes after staging measurements.

Do not provide long-lived AWS keys to application tasks. Use ECS task roles,
Secrets Manager/Parameter Store, private networking, and narrowly scoped
deployment roles.

## 8. Go-live sequence

1. Approve owner decisions and provision isolated AWS staging through reviewed
   infrastructure as code.
2. Deploy with every scalability flag off and confirm health, Store isolation,
   queues, uploads, backups, and restore procedure.
3. Enable instrumentation, establish baseline, and run the read-only smoke
   profile.
4. Enable one cache flag, repeat the profile, and compare query volume, latency,
   and error rate.
5. Enable rate limits generously and validate expected `429` behavior.
6. Run target, spike, and soak profiles; tune resources from evidence.
7. Test RDS, Redis, and ECS task failure before production promotion.
8. Promote the immutable image, run one controlled migration task, watch the
   rollout, and retain the previous task definition.

The result is a measured capacity statement for one explicit AWS
configuration. Re-run it after material Product Detail providers, query
patterns, queue workloads, or instance families change.
