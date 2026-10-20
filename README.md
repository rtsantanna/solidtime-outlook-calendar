# solidtime — Outlook calendar import

> **This is a fork.** It is [solidtime](https://github.com/solidtime-io/solidtime) **v0.21.0** plus an **Outlook
> calendar import**: each user connects their own Microsoft 365 calendar and turns past meetings into time entries.
> Everything else is upstream solidtime; the original README follows below. Em português: [LEIA-ME.md](LEIA-ME.md).

## Why this exists

Meetings are a large part of the week for many teams, and typing them in again by hand is exactly what makes people
stop tracking time. The calendar already has the subject, the start and the end. This fork reads it and turns each
meeting into a time entry, so recording a week of meetings takes a minute.

It answers the request in [discussion #358](https://github.com/solidtime-io/solidtime/discussions/358) for Microsoft
365 users. The design is read-only and per user, and it was kept close to what a Google Calendar connection would
need, so the two can share one shape.

## What it does

- **Connect Outlook:** each user connects their own calendar from the Time page (Microsoft Entra ID, delegated,
  read-only, authorization code with PKCE).
- **Import from calendar:** lists the meetings since the last import, without cancelled, all-day, declined or "free"
  events. Each meeting comes with a suggested project, task and tags, learned from the user's own past entries.
- **Import or ignore:** the user picks the meetings to keep. Each one becomes a normal, non-billable time entry.
  - A meeting is never imported twice.
  - The organization's "prevent overlapping time entries" rule is respected.
  - Ignored meetings stop showing up as new.
- **Off by default:** without `ENTRA_CALENDAR_ENABLED=true` and the Entra app values, nothing shows and the routes
  answer 404.

Setup, security notes and the API: **[OUTLOOK-CALENDAR.md](OUTLOOK-CALENDAR.md)**.

## Changes compared to upstream

- New service, two controllers, two models and two migrations. Three API routes and three web routes.
- One button and one dialog on the Time page.
- No change to existing tables or behaviour.
- **Based on [solidtime-pt-br](https://github.com/rtsantanna/solidtime-pt-br):** the UI strings go through the
  translation functions, which come from there. That is the only reason this fork depends on it; the feature works in
  any language. Portuguese strings for the feature are included.

## Verified

Built and tested on solidtime v0.21.0 with PHP 8.3 (October 2026): PHP test suite 1,156/1,156 (24 of them for this
feature, with Microsoft Graph and Entra ID faked), PHPStan with no errors, Pint, Vitest 204/204, vue-tsc, ESLint with
no errors and the production build.

## Related

- [solidtime-oidc](https://github.com/rtsantanna/solidtime-oidc): generic OIDC single sign-on.
- [solidtime-pt-br](https://github.com/rtsantanna/solidtime-pt-br): internationalization and Brazilian Portuguese.

## License and credits

GNU AGPL-3.0, the same license as solidtime ([LICENSE.md](LICENSE.md)). All credit for the application goes to the
[solidtime team](https://github.com/solidtime-io/solidtime).

---

# solidtime - The modern Open-Source TimeTracker

[![GitHub License](https://img.shields.io/github/license/solidtime-io/solidtime?style=flat-square)](https://github.com/solidtime-io/solidtime/blob/main/LICENSE.md)
[![Codecov](https://img.shields.io/codecov/c/github/solidtime-io/solidtime?style=flat-square&logo=codecov)](https://codecov.io/gh/solidtime-io/solidtime)
![GitHub Actions Unit Tests Status](https://img.shields.io/github/actions/workflow/status/solidtime-io/solidtime/phpunit.yml?style=flat-square)
![PHPStan badge](https://img.shields.io/badge/PHPStan-Level_7-blue?style=flat-square&color=blue)

![Screenshot of the solidtime application with header: solidtime - The modern Open-Source Time Tracker](docs/solidtime-banner.png "solidtime Banner")

solidtime is a modern open-source time tracking application for Freelancers and Agencies.

## Features

 - Time tracking: Track your time with a modern and easy-to-use interface
 - Projects: Create and manage projects and assign project members
 - Tasks: Create and manage tasks and assign tasks to projects
 - Clients: Create and manage clients and assign clients to projects
 - Billable rates: Set billable rates for projects, project members, organization members and organizations 
 - Multiple organizations: Create and manage multiple organizations with one account
 - Roles and permissions: Create and manage organizations
 - Import: Import your time tracking data from other time tracking applications (Supported: Toggl, Clockify, Timeentry CSV)

## Self Hosting

If you are looking into self-hosting solidtime, you can find the guides [here](https://docs.solidtime.io/self-hosting/intro)

We also have an examples repository [here](https://github.com/solidtime-io/self-hosting-examples)

If you do not want to self-host solidtime or try it out you can sign up for [solidtime cloud](https://www.solidtime.io/)

## Issues & Feature Requests

If you find any **bugs in solidtime**, please feel free to [**open an issue**](https://github.com/solidtime-io/solidtime/issues/new) in this repository, with instructions on how to reproduce the bug. 
If you have a **feature request**, please [**create a discussion**](https://github.com/solidtime-io/solidtime/discussions/new?category=feature-requests) in this repository.

## Contributing

Please open an issue or start a discussion and wait for approval before submitting a pull request. This does not apply to tiny fixes or changes however, please keep in mind that we might not merge PRs for various reasons. 

**If you submit an AI slop pull request (especially without following the proper procedure), you will be banned from future contributions to solidtime.**

To keep that manageable, pull requests from authors who are not vouched are closed automatically, unless they change 50 lines or fewer. To get vouched, open an issue or discussion first and explain how you intend to implement the change. Once we have agreed on the approach, we vouch for you. See [Vouched contributors](./CONTRIBUTING.md#vouched-contributors).

Please read the [CONTRIBUTING.md](./CONTRIBUTING.md) before sumbitting a Pull Request.

We do accept contributions in the [documentation repository](https://github.com/solidtime-io/docs) f.e. to add new self-hosting guides.

## Security

Looking to report a vulnerability? Please refer our [SECURITY.md](./SECURITY.md) file.

## License

This project is open-source and available under the GNU Affero General Public License v3.0 (AGPL v3). Please see the [license file](LICENSE.md) for more information.
