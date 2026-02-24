# WP Multisite Network DB Optimizer

## 🛠 Problem it Solves
Managing database bloat in a large-scale WordPress Multisite network (100+ sites) is a nightmare. Standard plugins often fail to address network-wide transients, autoload overhead, and orphaned metadata across multiple tables. 

This tool provides a **centralized command center** for Network Administrators to maintain peak database performance without accessing individual site dashboards.

## 🚀 Key Technical Features
* **Mass Aggregation:** Queries and aggregates stats (autoload size, revisions, spam) from all network sites using optimized `switch_to_blog` logic.
* **Smart Cleanup:** Intelligent deletion of expired transients and auto-drafts older than 7 days to prevent data loss.
* **Performance Monitoring:** Real-time analysis of `wp-config.php` constants with actionable recommendations for object caching (Redis/Memcached).
* **Automated Maintenance:** Integrated WP-Cron scheduling for weekly background optimization.

## 💻 Technical Stack
* **Language:** PHP
* **Platform:** WordPress Multisite
* **Database:** MySQL / MariaDB (Optimized queries for large tables)

## ⚖️ License
MIT - Created for enterprise-level WordPress environments.
