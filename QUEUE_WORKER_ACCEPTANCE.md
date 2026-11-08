# Samith AI — Queue worker supervisor acceptance

Default shared-hosting installation uses QUEUE_CONNECTION=sync, which cannot prove background-worker availability.

On an isolated, approved aaPanel staging clone only:
1. Configure QUEUE_CONNECTION=database, keep the matching jobs and worker_probes migrations, and set a queue worker service supervised through aaPanel Supervisor/systemd.
2. Dispatch a one-time probe using \`php artisan samith:queue-probe --dispatch\`. It prints a probe UUID and queues a content-free job.
3. Verify it is pending before the worker runs; start the supervised worker; check it using \`php artisan samith:queue-probe --check=UUID\`.
4. Restart PHP/worker process, enqueue a new probe and check it again. Prove Supervisor restarts it after a crash and respects resource/time limits.
5. Record operator, timestamps, actual service configuration, queue liveness and retry/failed-job evidence. Monitoring a count alone is not proof of process supervision.
6. Queue retry_after must exceed worker --timeout, and handlers need idempotent domain operations. Never enable money-related jobs before merchant/provider review.

Suggested staging worker invocation as a **supervisor-managed** program, not as an unmanaged shell background job:

    php artisan queue:work database --queue=default --sleep=3 --tries=3 --timeout=60 --max-time=3600

For cPanel without persistent process supervision, keep sync mode or use an independently assessed scheduled queue method with controlled locking and a verified cadence. Do not claim that a shared-hosting cron job is a persistent supervisor.

The GitHub Actions one-shot worker test demonstrates that enqueueing is not treated as processing. It is a synthetic CI check, not production worker acceptance.
