import type { InertiaLinkProps } from '@inertiajs/react';
import { usePage } from '@inertiajs/react';
import { toUrl } from '@/lib/utils';

export function useCurrentUrl() {
    const page = usePage();
    const currentUrlPath = new URL(
        page.url,
        typeof window !== 'undefined'
            ? window.location.origin
            : 'http://localhost',
    ).pathname;

    const isCurrentUrl = (
        urlToCheck: NonNullable<InertiaLinkProps['href']>,
        currentUrl?: string,
        startsWith = false,
    ): boolean => {
        const urlToCompare = currentUrl ?? currentUrlPath;
        const urlString = toUrl(urlToCheck);
        const comparePath = (path: string): boolean =>
            startsWith ? urlToCompare.startsWith(path) : path === urlToCompare;

        if (!urlString.startsWith('http')) {
            return comparePath(urlString);
        }

        try {
            return comparePath(new URL(urlString).pathname);
        } catch {
            return false;
        }
    };

    const isCurrentOrParentUrl = (
        urlToCheck: NonNullable<InertiaLinkProps['href']>,
        currentUrl?: string,
    ): boolean => {
        const urlToCompare = currentUrl ?? currentUrlPath;
        const path = toUrl(urlToCheck);
        const pathname = path.startsWith('http')
            ? (() => {
                  try {
                      return new URL(path).pathname;
                  } catch {
                      return path;
                  }
              })()
            : path;

        if (pathname === urlToCompare) {
            return true;
        }

        return urlToCompare.startsWith(
            pathname.endsWith('/') ? pathname : `${pathname}/`,
        );
    };

    const isNavItemActive = (
        urlToCheck: NonNullable<InertiaLinkProps['href']>,
        siblingHrefs: Array<NonNullable<InertiaLinkProps['href']>>,
        currentUrl?: string,
    ): boolean => {
        const urlToCompare = currentUrl ?? currentUrlPath;

        if (!isCurrentOrParentUrl(urlToCheck, urlToCompare)) {
            return false;
        }

        const selfPath = toUrl(urlToCheck);
        const hasMoreSpecificSibling = siblingHrefs.some((sibling) => {
            const siblingPath = toUrl(sibling);
            if (siblingPath === selfPath || siblingPath.length <= selfPath.length) {
                return false;
            }

            return isCurrentOrParentUrl(sibling, urlToCompare);
        });

        return !hasMoreSpecificSibling;
    };

    return {
        currentUrl: currentUrlPath,
        isCurrentUrl,
        isCurrentOrParentUrl,
        isNavItemActive,
    };
}
