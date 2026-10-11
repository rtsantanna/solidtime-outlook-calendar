import dayjs from 'dayjs';
import 'dayjs/locale/pt-br';

// Translation files live in lang/<code>.json (Laravel convention, e.g. pt_BR).
// Map each code to the dayjs locale that provides month and weekday names.
// Adding a language: one entry here plus its dayjs locale import above.
const DAYJS_LOCALES: Record<string, string> = {
    pt_BR: 'pt-br',
};

export function normalizeLanguage(lang: string | null | undefined): string {
    return (lang || 'en').replace('-', '_');
}

export function setDayjsLanguage(lang: string): void {
    dayjs.locale(DAYJS_LOCALES[lang] ?? 'en');
}
