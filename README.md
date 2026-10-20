# solidtime — internationalized, with Brazilian Portuguese (pt-BR)

> **This is a fork.** It is [solidtime](https://github.com/solidtime-io/solidtime) **v0.21.0** plus interface
> internationalization and a complete **Brazilian Portuguese** translation. Everything else is upstream solidtime;
> the original README follows below. Em português: [LEIA-ME.md](LEIA-ME.md).

## Why this exists

The solidtime interface is English-only: most of the text lives hardcoded in the Vue templates and in a few PHP
classes, so there is no way to translate it by dropping in a language file. This fork routes those strings through
the standard Laravel/Vue translation functions and ships a reviewed pt-BR catalog, so a Brazilian team gets every
screen, e-mail, report and PDF in Portuguese. English stays the default, and any missing key falls back to English.

## What changes

- **Frontend:** [laravel-vue-i18n](https://github.com/xiCO2k/laravel-vue-i18n) (the only new dependency) reads
  `lang/<code>.json`. The keys are the English source strings. About 830 hardcoded strings in templates, scripts and
  notifications now go through `$t()` / `trans()` / `$tChoice()`. The language comes from `<html lang>`, and the
  dayjs month and weekday names follow it.
- **Backend:** `APP_LOCALE` and `APP_FALLBACK_LOCALE` environment variables. Role names and descriptions, importer
  errors, report PDFs and spreadsheets, the OAuth consent screen and a few request and controller messages go
  through `__()`.
- **pt-BR:** `lang/pt_BR.json` (1,165 keys) and `lang/pt_BR/*.php`. The framework strings come from
  [laravel-lang](https://github.com/Laravel-Lang/lang); the project's own strings were translated and reviewed by hand.
- **No database migration**, and no behaviour change when `APP_LOCALE` is not set.

## How to use

Build and deploy exactly as upstream solidtime (`docker/prod`, see the self-hosting guide linked below), and set:

```env
APP_LOCALE=pt_BR
APP_FALLBACK_LOCALE=en
```

The language is per installation, not per user.

## Adding another language

Copy `lang/pt_BR.json` to `lang/<code>.json` and translate the values, keeping the keys (the English text) as they
are. Then add `lang/<code>/*.php` (framework files from laravel-lang plus the project files `enum.php`, `enums.php`,
`exceptions.php` and `importer.php`) and set `APP_LOCALE=<code>`.

## Verified

Built and tested on solidtime v0.21.0 with PHP 8.3, the version upstream requires (October 2026): PHP test suite
1,132/1,132, PHPStan with no errors, Pint, Vitest 204/204, vue-tsc, ESLint with no errors and the production build.

## Related

- [solidtime-oidc](https://github.com/rtsantanna/solidtime-oidc): generic OIDC single sign-on for solidtime. Both
  forks are kept small and rebasable, and they combine without conflicts on v0.21.0.
- Upstream: the goal is to make this useful to solidtime itself. Feedback from the maintainers on the preferred way
  to contribute it (as is, or split into smaller pieces) is welcome.

## License and credits

GNU AGPL-3.0, the same license as solidtime ([LICENSE.md](LICENSE.md)). All credit for the application goes to the
[solidtime team](https://github.com/solidtime-io/solidtime). The framework translation strings come from
[laravel-lang](https://github.com/Laravel-Lang/lang) (MIT).

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
