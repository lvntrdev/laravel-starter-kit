import js from '@eslint/js';
import pluginVue from 'eslint-plugin-vue';
import tseslint from 'typescript-eslint';

// Flat-config ESLint setup for the starter kit's Vue 3 + TypeScript frontend.
// `npm run lint` (eslint resources) uses this; tune the `rules` block to taste.
export default tseslint.config(
    {
        // Generated / build output — never lint these.
        ignores: [
            'resources/js/wayfinder/**',
            'resources/js/routes/**',
            'resources/js/actions/**',
            '**/*.d.ts',
            'public/**',
            'bootstrap/ssr/**',
        ],
    },
    js.configs.recommended,
    ...tseslint.configs.recommended,
    ...pluginVue.configs['flat/essential'],
    {
        files: ['**/*.vue'],
        languageOptions: {
            parserOptions: {
                parser: tseslint.parser,
            },
        },
    },
    {
        rules: {
            '@typescript-eslint/no-explicit-any': 'off',
            '@typescript-eslint/no-unused-vars': [
                'warn',
                { argsIgnorePattern: '^_', varsIgnorePattern: '^_' },
            ],
            'vue/multi-word-component-names': 'off',
        },
    },
);
