# ADR 004: Sanctum and layered authorization

Status: accepted

Sanctum provides stateful cookies and tenant-scoped bearer tokens without OAuth refresh tokens. Authorization is layered across token abilities, Spatie teams/permissions, and Laravel policies, with an explicit global platform-admin gate.
