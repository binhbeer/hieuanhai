export const BRIDGE_VERSION = 1;

const EXTERNAL_SCHEMES = new Set(['mailto:', 'tel:', 'sms:']);

export type UrlDisposition = 'internal' | 'external' | 'blocked';

export function requireHttpsOrigin(rawUrl: string): string {
  const url = new URL(rawUrl);

  if (
    url.protocol !== 'https:' ||
    url.username ||
    url.password ||
    url.pathname !== '/' ||
    url.search ||
    url.hash
  ) {
    throw new Error('EXPO_PUBLIC_WEB_URL must be an exact HTTPS origin.');
  }

  return url.origin;
}

export function classifyUrl(rawUrl: string, allowedOrigin: string): UrlDisposition {
  let url: URL;

  try {
    url = new URL(rawUrl);
  } catch {
    return 'blocked';
  }

  if (url.protocol === 'https:') {
    return url.origin === allowedOrigin ? 'internal' : 'external';
  }

  return EXTERNAL_SCHEMES.has(url.protocol) ? 'external' : 'blocked';
}

export function mapDeepLink(rawUrl: string, allowedOrigin: string): string | null {
  let url: URL;

  try {
    url = new URL(rawUrl);
  } catch {
    return null;
  }

  if (url.protocol === 'https:') {
    return url.origin === allowedOrigin ? url.toString() : null;
  }

  if (url.protocol !== 'genanh:' || url.username || url.password || url.port) {
    return null;
  }

  const hostPath = url.hostname ? `/${url.hostname}` : '';
  const pathname = url.pathname === '/' ? '' : url.pathname;
  const targetPath = `${hostPath}${pathname}` || '/';

  return new URL(`${targetPath}${url.search}${url.hash}`, allowedOrigin).toString();
}

export function resolveBridgeMediaUrl(rawUrl: string, allowedOrigin: string): string | null {
  if (classifyUrl(rawUrl, allowedOrigin) !== 'internal') {
    return null;
  }

  const url = new URL(rawUrl);
  const isDownloadPath = /^\/(?:anh|vi\/anh|en\/images)\/[^/]+\/download$/.test(url.pathname);
  const expires = url.searchParams.get('expires');
  const signature = url.searchParams.get('signature');

  return isDownloadPath
    && expires !== null
    && /^\d+$/.test(expires)
    && signature !== null
    && /^[a-f0-9]{64}$/i.test(signature)
    ? url.toString()
    : null;
}
