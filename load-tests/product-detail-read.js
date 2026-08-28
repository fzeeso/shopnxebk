import http from 'k6/http';
import { check, group, sleep } from 'k6';
import { SharedArray } from 'k6/data';
import { Rate, Trend } from 'k6/metrics';

const baseUrl = (__ENV.BASE_URL || 'http://127.0.0.1:8000').replace(/\/$/, '');
const storesFile = __ENV.STORES_FILE || './stores.example.json';
const targetRate = positiveInteger(__ENV.TARGET_RPS, 5);
const preAllocatedVUs = positiveInteger(__ENV.PRE_ALLOCATED_VUS, 10);
const maxVUs = positiveInteger(__ENV.MAX_VUS, 100);
const stageDuration = __ENV.STAGE_DURATION || '1m';
const selectedSections = __ENV.SECTIONS || 'product,images,variants';
const includeReferenceData = (__ENV.WITH_REFERENCE_DATA || 'false').toLowerCase() === 'true';

const stores = new SharedArray('authorized staging stores', () => {
  const parsed = JSON.parse(open(storesFile));

  if (!Array.isArray(parsed.stores) || parsed.stores.length === 0) {
    throw new Error('STORES_FILE must contain a non-empty stores array.');
  }

  return parsed.stores;
});

const applicationDuration = new Trend('shopnxe_application_duration', true);
const contractFailures = new Rate('shopnxe_contract_failures');

export const options = {
  scenarios: {
    product_detail_reads: {
      executor: 'ramping-arrival-rate',
      startRate: 1,
      timeUnit: '1s',
      preAllocatedVUs,
      maxVUs,
      stages: [
        { target: targetRate, duration: stageDuration },
        { target: targetRate, duration: stageDuration },
        { target: 0, duration: '30s' },
      ],
      gracefulStop: '30s',
    },
  },
  thresholds: {
    http_req_failed: ['rate<0.01'],
    http_req_duration: ['p(95)<1000', 'p(99)<2000'],
    shopnxe_application_duration: ['p(95)<1000'],
    shopnxe_contract_failures: ['rate<0.01'],
  },
  discardResponseBodies: false,
};

export default function () {
  const store = stores[Math.floor(Math.random() * stores.length)];
  const productIds = Array.isArray(store.product_ids) ? store.product_ids : [];
  const headers = {
    Accept: 'application/json',
    Authorization: `Bearer ${store.token}`,
    'X-Store-ID': store.store_id,
  };

  if (productIds.length === 0 || Math.random() < 0.2) {
    group('product detail bootstrap', () => {
      record(http.get(
        `${baseUrl}/api/v1/store/product-detail?sections=${encodeURIComponent(selectedSections)}&reference_limit=100`,
        { headers, tags: { operation: 'product_detail_bootstrap' } },
      ));
    });
  } else {
    const productId = productIds[Math.floor(Math.random() * productIds.length)];
    group('product detail show', () => {
      const query = [
        `sections=${encodeURIComponent(selectedSections)}`,
        `with_reference_data=${includeReferenceData ? '1' : '0'}`,
        'section_limit=100',
        'reference_limit=100',
      ].join('&');
      record(http.get(
        `${baseUrl}/api/v1/store/product-detail/${productId}?${query}`,
        { headers, tags: { operation: 'product_detail_show' } },
      ));
    });
  }

  sleep(Number(__ENV.THINK_TIME_SECONDS || 0.2));
}

function record(response) {
  const valid = check(response, {
    'status is 200': (value) => value.status === 200,
    'response has data': (value) => {
      try {
        return JSON.parse(value.body).data !== undefined;
      } catch (_) {
        return false;
      }
    },
  });

  contractFailures.add(!valid);
  const serverTiming = response.headers['Server-Timing'];
  if (serverTiming) {
    const match = /app;dur=([0-9.]+)/.exec(serverTiming);
    if (match) {
      applicationDuration.add(Number(match[1]));
    }
  }
}

function positiveInteger(value, fallback) {
  const parsed = Number.parseInt(value || '', 10);

  return Number.isFinite(parsed) && parsed > 0 ? parsed : fallback;
}
