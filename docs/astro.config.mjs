import { defineConfig } from 'astro/config';
import starlight from '@astrojs/starlight';
import AutoImport from 'astro-auto-import';
import mdx from '@astrojs/mdx';

export default defineConfig({
    integrations: [
        AutoImport({
            imports: [
                {
                    './src/components/docs/ApiSignature.astro': [['default', 'ApiSignature']],
                    './src/components/docs/ApiParam.astro': [['default', 'ApiParam']],
                    './src/components/docs/ApiThrows.astro': [['default', 'ApiThrows']],
                    './src/components/docs/ApiProperty.astro': [['default', 'ApiProperty']],
                    './src/components/docs/ApiMethod.astro': [['default', 'ApiMethod']],
                    './src/components/docs/ApiBadge.astro': [
                        ['default', 'ApiBadge'],
                        ['default', 'ApplicationBadge']
                    ],
                    './src/components/docs/ScopeBox.astro': [
                        ['default', 'ScopeBox'],
                        ['default', 'Scope']
                    ],
                    './src/components/docs/NoteBox.astro': [
                        ['default', 'NoteBox'],
                        ['default', 'Note']
                    ],
                    './src/components/docs/WarningBox.astro': [
                        ['default', 'WarningBox'],
                        ['default', 'Warning']
                    ],
                    './src/components/docs/LifecycleFlow.astro': [['default', 'LifecycleFlow']],
                }
            ]
        }),
        starlight({
            title: 'SKIM Framework',
            description: 'PHP 8.5+ micro-framework — auto-generated API reference',
            social: [
                { icon: 'github', label: 'GitHub', href: 'https://github.com/skim-framework/skim' },
            ],
            sidebar: [
                {
                    label: 'Getting Started',
                    items: [
                        { label: 'Overview', slug: 'index' },
                    ],
                },
                {
                    label: 'API Reference',
                    autogenerate: { directory: 'api' },
                },
            ],
            customCss: ['./src/styles/docs-api.css'],
        }),
        mdx(),
    ],
});
