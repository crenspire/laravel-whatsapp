import { defineConfig } from 'vitepress'

const repo = 'https://github.com/crenspire/laravel-whatsapp'

export default defineConfig({
  title: 'Laravel WhatsApp',
  description: "Send and receive WhatsApp messages from Laravel with Meta's WhatsApp Business Cloud API.",
  base: '/laravel-whatsapp/',
  cleanUrls: true,
  lastUpdated: true,
  srcExclude: ['README.md'],

  markdown: {
    config(md) {
      // CHANGELOG.md and UPGRADE.md are included from the repo root, where they
      // link to each other by file name. Point those links at the site pages.
      const siteLinks: Record<string, string> = { 'UPGRADE.md': '/upgrade', 'CHANGELOG.md': '/changelog' }
      const render = md.renderer.rules.link_open!

      md.renderer.rules.link_open = (tokens, idx, options, env, self) => {
        const href = tokens[idx].attrGet('href')

        if (href && siteLinks[href]) {
          tokens[idx].attrSet('href', siteLinks[href])
        }

        return render(tokens, idx, options, env, self)
      }
    },
  },

  head: [
    ['link', { rel: 'icon', type: 'image/svg+xml', href: '/laravel-whatsapp/logo.svg' }],
    ['meta', { name: 'theme-color', content: '#128c4a' }],
    ['meta', { property: 'og:type', content: 'website' }],
    ['meta', { property: 'og:title', content: 'Laravel WhatsApp' }],
    ['meta', { property: 'og:description', content: "Send and receive WhatsApp messages from Laravel with Meta's WhatsApp Business Cloud API." }],
  ],

  themeConfig: {
    logo: '/logo.svg',

    nav: [
      { text: 'Guide', link: '/guide/installation', activeMatch: '/guide/' },
      { text: 'Upgrade to 3.0', link: '/upgrade' },
      { text: 'Changelog', link: '/changelog' },
      { text: 'Packagist', link: 'https://packagist.org/packages/crenspire/laravel-whatsapp' },
    ],

    sidebar: [
      {
        text: 'Getting started',
        items: [
          { text: 'Installation and setup', link: '/guide/installation' },
          { text: 'Sending messages', link: '/guide/sending-messages' },
        ],
      },
      {
        text: 'Features',
        items: [
          { text: 'Notifications', link: '/guide/notifications' },
          { text: 'Webhooks and events', link: '/guide/webhooks' },
          { text: 'Templates', link: '/guide/templates' },
          { text: 'Media', link: '/guide/media' },
          { text: 'Errors and retries', link: '/guide/errors' },
          { text: 'Testing', link: '/guide/testing' },
          { text: 'Message log', link: '/guide/message-log' },
          { text: 'Artisan commands', link: '/guide/commands' },
          { text: 'Multiple phone numbers', link: '/guide/multiple-numbers' },
          { text: 'Configuration reference', link: '/guide/configuration' },
        ],
      },
      {
        text: 'Releases',
        items: [
          { text: 'Upgrading from 2.x', link: '/upgrade' },
          { text: 'Changelog', link: '/changelog' },
        ],
      },
    ],

    socialLinks: [{ icon: 'github', link: repo }],

    search: { provider: 'local' },

    editLink: {
      pattern: `${repo}/edit/develop/website/:path`,
      text: 'Edit this page on GitHub',
    },

    footer: {
      message: 'Released under the MIT License. Not affiliated with Meta or WhatsApp.',
      copyright: 'Copyright © 2025 Crenspire',
    },
  },
})
