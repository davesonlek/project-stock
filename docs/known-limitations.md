# Known Limitations & Prototype Boundaries

This project is an **Enterprise Stock Management Prototype & Proof-of-Architecture**, designed to validate ACID transaction integrity, pessimistic concurrency locking, and immutable ledger patterns. 

---

## 1. Prototype vs. Full Production ERP Boundaries

| Boundary Item | Prototype Status | Recommended Production Implementation |
| :--- | :--- | :--- |
| **High Availability (HA)** | Single PostgreSQL instance | PostgreSQL Patroni / AWS Aurora Multi-AZ with Read Replicas |
| **Automated FEFO / FIFO Picking** | Manual lot selection in UI/API | Domain allocation service with automatic FEFO batch picking algorithms |
| **ERP / PO / SO Integration** | Standalone stock transactions | Webhook / Event-driven asynchronous messaging (RabbitMQ / Kafka) |
| **Mobile / Handheld PDA Native App** | Responsive Web UI (Blade + Bootstrap 5) | Dedicated Flutter or React Native barcode scanning mobile application |
| **Automated WAL Archival / PITR** | Standard DB backups | Point-in-Time-Recovery (PITR) with continuous WAL shipping to S3/GCS |
| **Real-time Push Notifications** | Database polling / refresh | Laravel Reverb / WebSockets for live collaborative document editing |
