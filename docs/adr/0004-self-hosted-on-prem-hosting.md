# Self-hosted, containerized, on-prem / private-cloud hosting

**Status:** accepted

The system is deployed as containers on university-controlled infrastructure (private cloud / on-premise), orchestrated by Kubernetes (HA Docker Swarm acceptable), rather than on public-cloud managed services. This keeps student PII — documents (MinIO), notifications (self-hosted Reverb), and database contents — inside the university's control boundary, consistent with FERPA / data-protection posture. Kubernetes provides the horizontal autoscaling and automated failover needed for the 5,000-concurrent-user, 99.9%-uptime, 60s-failover NFRs; PostgreSQL runs primary + synchronous replica in-cluster.

## Considered options

- **Self-hosted containers + orchestration (chosen).** PII stays in-house; HA met via orchestration and DB replication.
- **Public cloud managed services (rejected).** RDS/ElastiCache/EKS would deliver HA with less ops effort, but move PII off-premises and would reopen the MinIO/Reverb/storage decisions; revisit only if a compliance review clears public-cloud PII storage.

## Consequences

- The team owns more operational burden (running Postgres HA, MinIO, ClamAV, Reverb) than a managed platform would require.
- Choosing the S3-compatible MinIO API keeps a later lift to cloud object storage possible without application changes.
- Hosting realities (available cluster capacity, DR site) should be confirmed with the university infrastructure owners before build-out.
