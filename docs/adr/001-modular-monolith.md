# ADR 001: Modular monolith

Status: accepted

Keep modules in one Laravel deployment while domain boundaries are still changing. Each module owns persistence and API contracts; cross-module behavior uses actions, contracts, and events. This keeps transactions and local development simple without allowing a shared-services dumping ground.
