<p align="center">
  <a href="https://escalated.dev"><img src="https://raw.githubusercontent.com/escalated-dev/escalated/main/docs/assets/readme/logo.png" width="56" height="56" alt="Escalated"></a>
</p>

<h1 align="center">NPS plugin for Escalated</h1>

<p align="center">Net Promoter Score surveys after every resolved ticket.</p>

<p align="center">
  <a href="https://escalated.dev"><b>Website</b></a> ·
  <a href="https://escalated.dev/docs"><b>Docs</b></a> ·
  <a href="https://escalated.dev/cloud"><b>Cloud</b></a> ·
  <a href="https://github.com/escalated-dev/escalated-plugin-nps/releases"><b>Releases</b></a>
</p>

This plugin sends an NPS survey after a ticket is resolved, scores the answers, and shows the results on a dashboard, a widget and each contact's history. It's for support teams that want to measure how customers feel without asking them too often.

## Features

<table>
<tr>
<td width="33%" valign="top">

**Surveys**
- Sent after resolution, with a configurable delay
- Throttled per contact to avoid survey fatigue
- Public response endpoint for email links

</td>
<td width="33%" valign="top">

**Scoring**
- Promoters, passives and detractors scored
- Admin notified on detractor responses

</td>
<td width="33%" valign="top">

**Reporting**
- NPS dashboard with score analytics
- Dashboard widget with live score
- NPS history on each contact
- NPS Score column in the ticket list

</td>
</tr>
</table>

<p align="center"><sub><a href="docs/features.md">Every feature in detail</a></sub></p>

<p align="center">
  <a href="https://www.typescriptlang.org"><img src="https://img.shields.io/badge/TypeScript-3178C6?style=for-the-badge&logo=typescript&logoColor=white" alt="TypeScript"></a>
  <a href="https://nodejs.org"><img src="https://img.shields.io/badge/Node.js-339933?style=for-the-badge&logo=nodedotjs&logoColor=white" alt="Node.js"></a>
  <a href="https://vuejs.org"><img src="https://img.shields.io/badge/Vue.js-4FC08D?style=for-the-badge&logo=vuedotjs&logoColor=white" alt="Vue.js"></a>
</p>

## Learn more

- [Every feature](docs/features.md)
- [Configuration, hooks and endpoints](docs/reference.md)
- [Plugin SDK](https://github.com/escalated-dev/escalated-plugin-sdk): how Escalated plugins are built
- [Escalated docs](https://escalated.dev/docs)
- [Escalated](https://github.com/escalated-dev/escalated), the main repo

## License

MIT. Copyright (c) 2026 Escalated.dev. See [LICENSE](LICENSE).
