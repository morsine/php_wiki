# Zabbix Notes

Quick reference notes for the monitoring stack.

## Common triggers

| Trigger | Meaning |
|---------|---------|
| High CPU | CPU load above threshold for 5m |
| Disk space low | Free space under 10% |
| Host unreachable | Agent not responding |

## Useful links

- [Zabbix documentation](https://www.zabbix.com/documentation/current/en/manual)

Remember to check the `events_summary` table after any backfill job.
