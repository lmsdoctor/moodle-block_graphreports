# block_graphreports

> **Status:** BETA — v0.1.1 | Requires Moodle 4.1+

A role-aware graphical reports block for the Moodle dashboard. Each user automatically sees a dashboard tailored to their role: site statistics for admins, classroom analytics for teachers, child progress for parents, and personal progress for students. All charts are rendered client-side with Chart.js v4.

---

## Features

- **4 role-based dashboards** — Admin, Teacher, Parent (Mentor), Student
- **Automatic role detection** — no manual configuration per user
- **Interactive charts** — Bar, Line, Doughnut, Radar via Chart.js v4
- **Universal KPI row** — shows total charts, charts with data, empty charts and data points on every dashboard
- **MUC caching** — query results cached with configurable TTL to reduce DB load
- **Configurable** — max charts per role and cache refresh window via plugin settings
- **GDPR compliant** — implements `null_provider`; no personal data stored

---

## Requirements

| Requirement | Version |
|---|---|
| Moodle | 4.1+ (`2022112800`) |
| PHP | 8.0+ |
| Database | MySQL 5.7+ / MariaDB 10.4+ / PostgreSQL 12+ |

---

## Installation

1. Copy the `graphreports` folder into your Moodle `blocks/` directory:
   ```
   /path/to/moodle/blocks/graphreports/
   ```

2. Log in as **Site Administrator** and navigate to:
   **Site Administration → Notifications**
   Moodle will detect the new plugin and run the installation automatically.

3. Add the block to any page (Dashboard, course page, etc.) via **Edit mode → Add a block → Graph reports**.

---

## Configuration

Navigate to **Site Administration → Plugins → Blocks → Graph reports**.

| Setting | Description | Default | Options |
|---|---|---|---|
| **Max reports per role** | Maximum number of charts displayed per dashboard | `5` | 3, 5, 7, 10 |
| **Cache refresh (minutes)** | How long report query results are cached | `60` | 15 min, 30 min, 1 hour, 2 hours |

---

## Dashboards by Role

Role detection is automatic. The block reads the user's capabilities and assigns the highest-matching role in this priority order:

```
Site Admin / Manager → Teacher / Editing Teacher → Parent (Mentor) → Student
```

### 🔵 Admin Dashboard
Shown to: users with `block/graphreports:viewadmindashboard` (managers, site admins).

| Chart | Type | Description |
|---|---|---|
| Logins — Last 120 days | Line | Daily login counts from the logstore |
| Enrollments per course | Bar | Total enrolments per visible course |
| Active vs Inactive users | Doughnut | Users active within 30 days vs inactive |
| Completions per course | Bar | Course completions count |
| Users who never logged in | Doughnut | Never-logged vs logged users |
| New registrations per month | Line | Monthly account creations |
| Enrollments per category | Pie | Total enrolments grouped by category |

### 🟢 Teacher Dashboard
Shown to: users with `block/graphreports:viewteacherdashboard` (teacher, editingteacher).

| Chart | Type | Description |
|---|---|---|
| My students per course | Bar | Enrolment count per course the teacher owns |
| Completion progress per course | Stacked bar | Completed vs in-progress per course |
| Inactive students (+4 weeks) | Bar | Students with no activity in over 4 weeks |
| Average grade per activity | Bar | Mean grade across gradable activities |
| Forum activity by student | Bar | Post counts per student in course forums |

### 🟡 Parent Dashboard
Shown to: users with `block/graphreports:viewparentdashboard` (requires a Mentor role assignment via **User context**).

| Chart | Type | Description |
|---|---|---|
| My child's course status | Doughnut | Completed / in progress / not started |
| My child's completion (%) | Bar | Completion percentage per course |
| Last login | Bar | Days since the child's last access |
| My child's grades | Radar | Recent activity grades |
| My child's pending activities | Doughnut | Completed vs pending activities |

> **Note:** The Parent dashboard requires a **role assignment at User context** (Mentor relationship). Set this up via **Site Administration → Users → Permissions → Assign system roles**, or via the user's profile page.

### 🔴 Student Dashboard
Shown to: users with `block/graphreports:viewstudentdashboard` (student archetype).

| Chart | Type | Description |
|---|---|---|
| My courses | Doughnut | Completed vs in-progress courses |
| My completion progress | Bar | Completion percentage per enrolled course |
| My grades | Radar | Recent activity grades |

---

## Capabilities

| Capability | Context | Default roles |
|---|---|---|
| `block/graphreports:addinstance` | Block | Manager, Editing Teacher |
| `block/graphreports:myaddinstance` | System | Authenticated user |
| `block/graphreports:viewadmindashboard` | System | Manager |
| `block/graphreports:viewteacherdashboard` | Course | Teacher, Editing Teacher |
| `block/graphreports:viewparentdashboard` | User | *(assign manually)* |
| `block/graphreports:viewstudentdashboard` | Course | Student |

---

## File Structure

```
blocks/graphreports/
├── block_graphreports.php        ← Block entry point (get_content, role routing)
├── settings.php                  ← Admin settings (max_reports, cache_ttl)
├── version.php                   ← Plugin version metadata
├── styles.css                    ← Block stylesheet
├── db/
│   ├── access.php                ← Capability definitions
│   └── caches.php                ← MUC cache definition (reportdata)
├── lang/en/
│   └── block_graphreports.php    ← English language strings
├── classes/
│   ├── role_detector.php         ← Detects user role (admin/teacher/parent/student)
│   ├── report_manager.php        ← SQL queries with MUC caching
│   ├── chart_renderer.php        ← Converts reports → Mustache context + Chart.js JSON
│   └── privacy/
│       └── provider.php          ← GDPR null provider
├── templates/
│   ├── dashboard_admin.mustache
│   ├── dashboard_teacher.mustache
│   ├── dashboard_parent.mustache
│   ├── dashboard_student.mustache
│   └── partials/
│       ├── kpi_card.mustache     ← KPI summary card
│       ├── chart_card.mustache   ← Chart.js canvas container
│       └── empty_state.mustache  ← No-data placeholder
└── amd/
    ├── src/charts.js             ← AMD module: Chart.js initialiser
    └── build/charts.min.js       ← Compiled output (Grunt)
```

---

## Development

### Recompile AMD after editing `amd/src/charts.js`

```bash
# From the Moodle root directory:
grunt amd --root=blocks/graphreports

# Or globally (slower):
grunt amd --force
```

Moodle serves `amd/build/charts.min.js` in production — the source file is never loaded directly.

### Purge caches after template or string changes

```
Site Administration → Development → Purge all caches
```

Or via CLI:
```bash
php admin/cli/purge_caches.php
```

---

## Changelog

### v0.1.1 — 2026-03-11 (BETA)
- Initial BETA release
- Role-based dashboards for Admin, Teacher, Parent and Student
- Universal KPI row across all dashboards
- MUC caching with configurable TTL
- Chart.js v4 AMD integration
- GDPR null provider

---

## License

GNU GPL v3 or later — http://www.gnu.org/copyleft/gpl.html
