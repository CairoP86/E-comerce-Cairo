<template>
    <svg class="st-tech-art" viewBox="0 0 700 520" fill="none" role="img" aria-label="Ilustración conceptual de una laptop; no representa un producto a la venta">
        <defs>
            <linearGradient id="screen" x1="140" y1="100" x2="540" y2="430" gradientUnits="userSpaceOnUse"><stop stop-color="var(--color-art-screen-start)"/><stop offset="1" stop-color="var(--color-art-screen-end)"/></linearGradient>
            <linearGradient id="metal" x1="100" y1="150" x2="590" y2="400" gradientUnits="userSpaceOnUse"><stop stop-color="var(--color-art-metal-light)"/><stop offset=".4" stop-color="var(--color-art-metal-mid)"/><stop offset="1" stop-color="var(--color-art-metal-end)"/></linearGradient>
            <linearGradient id="ribbon" x1="260" y1="140" x2="440" y2="340" gradientUnits="userSpaceOnUse"><stop stop-color="var(--color-art-ribbon-start)"/><stop offset=".6" stop-color="var(--color-art-ribbon-mid)"/><stop offset="1" stop-color="var(--color-art-ribbon-end)"/></linearGradient>
            <filter id="shadow"><feGaussianBlur stdDeviation="12"/></filter>
        </defs>
        <ellipse cx="355" cy="432" rx="250" ry="27" fill="var(--color-art-shadow)" opacity=".7" filter="url(#shadow)"/>
        <g transform="rotate(-7 350 270)">
            <rect x="151" y="93" width="416" height="293" rx="15" fill="url(#metal)"/>
            <rect x="157" y="99" width="404" height="280" rx="10" fill="var(--color-navy)"/>
            <rect x="167" y="111" width="384" height="251" rx="4" fill="url(#screen)"/>
            <path d="M210 344C289 297 247 118 367 144C481 169 283 279 421 324C448 333 493 314 529 278" stroke="url(#ribbon)" stroke-width="52"/>
            <path d="M151 386L83 418C78 421 79 428 86 430L553 447C569 447 611 424 616 417L567 386H151Z" fill="url(#metal)"/>
            <path d="M168 391L120 413L557 425L587 412L550 392Z" fill="var(--color-navy-raised)"/>
            <path d="M280 416L267 425L411 430L422 420" fill="var(--color-art-metal-end)"/>
            <path d="M88 433L552 451C570 451 608 434 616 420" stroke="var(--color-navy-raised)" stroke-width="4"/>
            <circle cx="359" cy="105" r="2" fill="var(--color-art-metal-mid)"/>
        </g>
        <circle cx="585" cy="136" r="49" stroke="var(--color-art-ribbon-start)" stroke-opacity=".25"/>
        <path d="M567 135h36m-17-18v36" stroke="var(--color-art-ribbon-start)" stroke-width="2"/>
        <circle cx="102" cy="319" r="5" fill="var(--color-highlight-on-dark)"/>
    </svg>
</template>
