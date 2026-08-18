import js from '@eslint/js';
import prettier from 'eslint-config-prettier';
import react from 'eslint-plugin-react';
import reactHooks from 'eslint-plugin-react-hooks';
import globals from 'globals';
import typescript from 'typescript-eslint';

/** @type {import('eslint').Linter.Config[]} */
export default [
    js.configs.recommended,
    ...typescript.configs.recommended,
    {
        ...react.configs.flat.recommended,
        ...react.configs.flat['jsx-runtime'], // Required for React 17+
        languageOptions: {
            globals: {
                ...globals.browser,
            },
        },
        rules: {
            'react/react-in-jsx-scope': 'off',
            'react/prop-types': 'off',
            'react/no-unescaped-entities': 'off',
        },
        settings: {
            react: {
                version: 'detect',
            },
        },
    },
    {
        plugins: {
            'react-hooks': reactHooks,
        },
        rules: {
            'react-hooks/rules-of-hooks': 'error',
            'react-hooks/exhaustive-deps': 'warn',
        },
    },
    {
        // Maintainer tooling scripts (*.cjs) are plain Node, not app
        // source — they don't run in the browser and use Node globals
        // (require, process, __dirname) the app's ruleset forbids.
        // `.release-build` is where build-release.sh assembles a zip: a full
        // copy of the app plus vendored, minified JS. It is gitignored, so
        // linting it only ever means `--fix` rewriting artifacts nobody reads.
        ignores: ['vendor', 'node_modules', 'public', 'bootstrap/ssr', 'tailwind.config.js', '.claude', 'packages', '.release-build'],
    },
    prettier, // Turn off all rules that might conflict with Prettier
];
