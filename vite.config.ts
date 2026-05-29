import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig } from 'vitest/config';

export default defineConfig({
    build: {
        rolldownOptions: {
            output: {
                codeSplitting: {
                    groups: [
                        {
                            name: 'vendor-react',
                            test: /node_modules[\\/](react|react-dom|scheduler)[\\/]/,
                            priority: 40,
                        },
                        {
                            name: 'vendor-inertia',
                            test: /node_modules[\\/](@inertiajs|laravel-precognition)[\\/]/,
                            priority: 30,
                        },
                        {
                            name: 'vendor-ui',
                            test: /node_modules[\\/](@radix-ui|@floating-ui|aria-hidden|react-remove-scroll|sonner)[\\/]/,
                            priority: 20,
                        },
                        {
                            name: 'vendor-utils',
                            test: /node_modules[\\/](class-variance-authority|clsx|es-toolkit|tailwind-merge|tslib)[\\/]/,
                            priority: 10,
                        },
                    ],
                },
            },
        },
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
            fonts: [
                bunny('Inter', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        inertia(),
        react({
            babel: {
                plugins: ['babel-plugin-react-compiler'],
            },
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
        }),
    ],
    test: {
        clearMocks: true,
        css: true,
        environment: 'jsdom',
        include: ['resources/js/**/*.test.{ts,tsx}'],
        setupFiles: ['resources/js/test/setup.ts'],
    },
});
