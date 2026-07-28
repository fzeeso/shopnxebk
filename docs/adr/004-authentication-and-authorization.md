# ADR 004: Sanctum and layered authorization

Status: accepted

Sanctum provides stateful cookies and Store-scoped bearer tokens without OAuth refresh tokens. Authorization is layered across token abilities, active membership, Spatie teams/permissions, and Laravel policies. The global `Super Admin` role is the only Gate bypass; platform and Store roles are extendable catalog data rather than boolean user types.
