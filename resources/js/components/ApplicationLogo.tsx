import { SVGAttributes } from 'react';

export default function ApplicationLogo(props: SVGAttributes<SVGElement>) {
    return (
        <svg viewBox="0 0 48 48" fill="none" aria-hidden="true" {...props}>
            <rect width="48" height="48" rx="12" fill="currentColor" />
            <path
                d="M24 37V19m0 9c-10 0-14-7-14-15 10 0 14 6 14 15Zm0-5c0-9 6-14 15-14 0 9-6 14-15 14Z"
                stroke="#f4f5ed"
                strokeWidth="3"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    );
}
