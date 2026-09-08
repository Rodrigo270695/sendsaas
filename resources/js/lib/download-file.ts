const XLSX_MIME =
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

function filenameFromDisposition(header: string | null, fallback: string): string {
    if (!header) {
        return fallback;
    }

    const utf = /filename\*=UTF-8''([^;]+)/i.exec(header);
    if (utf?.[1]) {
        try {
            return decodeURIComponent(utf[1]);
        } catch {
            /* keep fallback */
        }
    }

    const ascii = /filename="?([^";]+)"?/i.exec(header);

    return ascii?.[1]?.trim() || fallback;
}

function looksLikeZip(bytes: Uint8Array): boolean {
    return bytes.length >= 2 && bytes[0] === 0x50 && bytes[1] === 0x4b;
}

/**
 * Descarga un XLSX por fetch + blob. Evita que Inertia o un HTML de error
 * se guarden como `plantilla.htm` al usar `<a download>` sin nombre.
 */
export async function downloadXlsx(url: string, fallbackFilename: string): Promise<void> {
    const response = await fetch(url, {
        method: 'GET',
        credentials: 'same-origin',
        headers: {
            Accept: `${XLSX_MIME},application/octet-stream`,
            'X-Requested-With': 'XMLHttpRequest',
        },
    });

    const buffer = await response.arrayBuffer();
    const bytes = new Uint8Array(buffer);

    if (!response.ok || !looksLikeZip(bytes)) {
        throw new Error('xlsx_download_failed');
    }

    const objectUrl = URL.createObjectURL(new Blob([buffer], { type: XLSX_MIME }));
    const link = document.createElement('a');
    link.href = objectUrl;
    link.download = filenameFromDisposition(
        response.headers.get('Content-Disposition'),
        fallbackFilename,
    );
    link.rel = 'noopener';
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.setTimeout(() => URL.revokeObjectURL(objectUrl), 1_000);
}
