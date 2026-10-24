import { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="-8 -8 110 164" xmlns="http://www.w3.org/2000/svg">
            <g fill="none" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round">
                <path strokeWidth="14" d="M0 0H44A34 34 0 0 1 44 68H0M0 68H54A40 40 0 0 1 54 148H0V0" />
                <path strokeWidth="8" d="M35 18V40H53" />
            </g>
            <circle cx="35" cy="40" r="6" fill="currentColor" />
        </svg>
    );
}
