import type { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            {...props}
            viewBox="0 0 24 24"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            aria-hidden="true"
        >
            <path
                d="M6.75 4.5H12.5C15.6756 4.5 18.25 7.07436 18.25 10.25V19.5H12.5C9.32436 19.5 6.75 16.9256 6.75 13.75V4.5Z"
                stroke="currentColor"
                strokeWidth="1.6"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
            <path
                d="M10 8V15.5L15 9.25V16"
                stroke="currentColor"
                strokeWidth="1.6"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
            <path
                d="M6.75 8.25H4.75C3.7835 8.25 3 9.0335 3 10V17.25C3 18.4926 4.00736 19.5 5.25 19.5H12.25"
                stroke="currentColor"
                strokeWidth="1.6"
                strokeLinecap="round"
                strokeLinejoin="round"
                opacity="0.55"
            />
            <path
                d="M18.25 8.25H19.25C20.2165 8.25 21 9.0335 21 10V17.25C21 18.4926 19.9926 19.5 18.75 19.5H18.25"
                stroke="currentColor"
                strokeWidth="1.6"
                strokeLinecap="round"
                strokeLinejoin="round"
                opacity="0.55"
            />
            <path
                d="M5.25 12.25H6.75M18.25 12.25H20.75M9.25 4.5V3.25M15.75 6.25L17 5"
                stroke="currentColor"
                strokeWidth="1.6"
                strokeLinecap="round"
            />
        </svg>
    );
}
