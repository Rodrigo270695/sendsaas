export function whatsAppHref(
    raw: string | null | undefined,
    text?: string,
): string | null {
    const digits = (raw ?? '').replace(/\D+/g, '');
    if (digits === '') {
        return null;
    }

    const e164 =
        digits.length === 9 && digits.startsWith('9') ? `51${digits}` : digits;

    const base = `https://wa.me/${e164}`;
    if (!text) {
        return base;
    }

    return `${base}?text=${encodeURIComponent(text)}`;
}
