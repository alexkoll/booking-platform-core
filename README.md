# Booking Platform Core

Production-style booking module for a service marketplace.

This repository contains a focused backend module responsible for creating, validating, reading, and protecting service bookings. It is structured as a DDD/CQRS-lite module and is intended to show how a real booking flow can be organized beyond simple CRUD.

## What It Does

- Creates online bookings from public provider pages
- Creates offline/manual bookings from provider calendar tools
- Validates provider, address, employee, and service availability
- Builds immutable service and terms snapshots
- Applies promo discounts for online bookings
- Resolves payment policy for deposit/full/no-payment flows
- Exposes client, provider, employee, calendar, and detail read models
- Dispatches asynchronous events after booking creation and status changes
- Protects against double booking with a PostgreSQL exclusion constraint

## Architecture

The module uses a pragmatic DDD/CQRS-lite structure.

```text
Booking
├─ Domain
│  ├─ Entity
│  ├─ ValueObject
│  ├─ Exception
│  └─ Repository
├─ Application
│  ├─ Command
│  ├─ Query
│  └─ Service
├─ Infrastructure
│  ├─ Doctrine
│  └─ ReadModel
└─ UI
   └─ Http
```

## Command Side

The command side is responsible for business decisions and state changes.

Booking creation is intentionally split into focused application services:

```text
CreateBookingHandler
├─ BookingCreationContextResolver
├─ BookingServiceSnapshotBuilder
├─ BookingPromoApplicator
├─ BookingAvailabilityChecker
├─ BookingPaymentPolicyResolver
├─ BookingTermsSnapshotBuilder
└─ BookingCreated event
```

The handler reads as a use case orchestration layer instead of containing all business logic itself.

## Availability

Availability checks are split by responsibility:

```text
BookingAvailabilityChecker
├─ BookingScheduleAvailability
├─ BookingSlotBlockChecker
└─ BookingOverlapChecker
```

Online and offline booking rules are kept separate because they intentionally have different behavior.

Online bookings validate public availability, schedule overrides, slot blocks, occupied slots, and past-time guards.

Offline bookings preserve provider-side manual behavior and allow past bookings while still preventing active overlaps.

## Read Side

Read endpoints use query handlers and reader ports instead of putting UI/list concerns into the domain repository.

```text
Controller
  -> Query Handler
      -> Reader Port
          -> Doctrine ReadModel implementation
```

Read models return API-ready arrays and batch-load enrichment data such as providers, addresses, users, profiles, services, and payment metadata.

Current read model implementations include:

```text
DoctrineCurrentUserBookingsReader
DoctrineProviderBookingsReader
DoctrineProviderBookingDetailsReader
DoctrineBookingSummaryReader
DoctrineBookingCalendarReader
DoctrineBookingCompatibilityReader
DoctrineBookingReadSideReader
```

## Repository Boundary

`BookingRepository` is kept focused on aggregate persistence and command-side business lookups.

It does not own paginated UI lists, profile summaries, provider filters, or API response mapping. Those responsibilities live in the query/read side.

## Race Condition Protection

Application-level availability checks provide fast feedback, but they are not the final safety guarantee.

The database protects against concurrent double booking with a PostgreSQL exclusion constraint:

```sql
EXCLUDE USING gist (
    provider_id WITH =,
    address_id WITH =,
    (COALESCE(employee_id, '')) WITH =,
    tsrange(
        booking_date + booking_time,
        booking_date + booking_time + (duration_minutes * INTERVAL '1 minute'),
        '[)'
    ) WITH &&
)
WHERE (status IN ('pending', 'confirmed'))
```

This makes overlapping active bookings impossible even under concurrent requests.

## Error Handling

The booking creation path uses typed exceptions for important business failures:

```text
BookingTermsNotAcceptedException
BookingSlotUnavailableException
BookingSlotBlockedException
EmployeeNotAvailableException
ServiceNotAvailableException
BookingPaymentPolicyException
```

HTTP controllers preserve public API response shapes while the application layer keeps errors explicit.

## Notes

This module was extracted from a larger service marketplace codebase. Public API contracts were preserved during refactoring while the internal structure was moved toward clearer DDD/CQRS-lite boundaries.
