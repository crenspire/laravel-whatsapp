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
      // CHANGELOG.md, UPGRADE.md and the release notes are included from the repo
      // root. On GitHub they link to each other by file name and to the site by
      // full URL; on the site those should be internal links.
      const siteLinks: Record<string, string> = {
        'UPGRADE.md': '/upgrade',
        'CHANGELOG.md': '/changelog',
        'RELEASE_NOTES_v3.0.0.md': '/whats-new',
      }
      const siteUrl = 'https://crenspire.github.io/laravel-whatsapp/'
      const render = md.renderer.rules.link_open!

      md.renderer.rules.link_open = (tokens, idx, options, env, self) => {
        const href = tokens[idx].attrGet('href')

        if (href && siteLinks[href]) {
          tokens[idx].attrSet('href', siteLinks[href])
        } else if (href?.startsWith(siteUrl)) {
          tokens[idx].attrSet('href', '/' + href.slice(siteUrl.length))
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
      { text: "What's new", link: '/whats-new' },
      {
        text: 'v3.0.0',
        items: [
          { text: "What's new in 3.0", link: '/whats-new' },
          { text: 'Upgrading from 2.x', link: '/upgrade' },
          { text: 'Changelog', link: '/changelog' },
          { text: 'All releases', link: `${repo}/releases` },
          { text: 'Packagist', link: 'https://packagist.org/packages/crenspire/laravel-whatsapp' },
        ],
      },
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
          { text: "What's new in 3.0", link: '/whats-new' },
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
