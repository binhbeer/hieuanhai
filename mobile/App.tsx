import { Directory, File, Paths } from 'expo-file-system';
import * as Sharing from 'expo-sharing';
import * as SplashScreen from 'expo-splash-screen';
import { StatusBar } from 'expo-status-bar';
import { useCallback, useEffect, useRef, useState } from 'react';
import {
  ActivityIndicator,
  BackHandler,
  Linking,
  Pressable,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { SafeAreaProvider, SafeAreaView } from 'react-native-safe-area-context';
import { WebView, type WebViewMessageEvent, type WebViewNavigation } from 'react-native-webview';
import {
  BRIDGE_VERSION,
  classifyUrl,
  mapDeepLink,
  requireHttpsOrigin,
  resolveBridgeMediaUrl,
} from './urlPolicy';

const WEB_ORIGIN = requireHttpsOrigin(process.env.EXPO_PUBLIC_WEB_URL ?? 'https://genanh.com');
const WEBVIEW_ORIGIN_WHITELIST = [
  'https://*',
  'http://*',
  'mailto:*',
  'tel:*',
  'sms:*',
  'file:*',
  'data:*',
  'javascript:*',
  'blob:*',
  'intent:*',
];
const SIGNED_URL_PATTERN = /^\/(?:anh|vi\/anh|en\/images)\/[^/]+\/download$/;
const LOAD_TIMEOUT_MS = 15_000;
const LIGHT_BACKGROUND = '#ffffff';
const DARK_BACKGROUND = '#18181b';

const BRIDGE_SCRIPT = `(() => {
  const VERSION = ${BRIDGE_VERSION};
  const post = (type, payload = null) => {
    window.ReactNativeWebView?.postMessage(JSON.stringify({ version: VERSION, type, payload }));
  };

  window.GenAnhNativeBridge = Object.freeze({
    version: VERSION,
    capabilities: Object.freeze(['downloadImage', 'shareImage']),
  });

  const sendTheme = () => post('themeChanged', {
    theme: document.documentElement.classList.contains('dark') ? 'dark' : 'light',
  });

  const observeTheme = () => {
    sendTheme();
    new MutationObserver(sendTheme).observe(document.documentElement, {
      attributes: true,
      attributeFilter: ['class'],
    });
    window.dispatchEvent(new CustomEvent('genanh:native-ready', {
      detail: window.GenAnhNativeBridge,
    }));
  };

  if (document.documentElement) observeTheme();
  else document.addEventListener('DOMContentLoaded', observeTheme, { once: true });
})()
true;`;

type MediaRequestType = 'downloadImage' | 'shareImage';

type MediaRequest = {
  version: number;
  type: MediaRequestType;
  requestId: string;
  payload: { url: string };
};

type ThemeRequest = {
  version: number;
  type: 'themeChanged';
  payload: { theme?: string } | null;
};

type BridgeRequest = MediaRequest | ThemeRequest;

type LoadError = {
  title: string;
  message: string;
};

SplashScreen.preventAutoHideAsync().catch(() => undefined);

function parseBridgeRequest(raw: string): BridgeRequest | null {
  if (!raw || raw.length > 8_192) return null;

  try {
    const value: unknown = JSON.parse(raw);
    if (!value || typeof value !== 'object') return null;

    const message = value as Record<string, unknown>;
    if (message.version !== BRIDGE_VERSION || typeof message.type !== 'string') return null;

    if (message.type === 'themeChanged') {
      return {
        version: BRIDGE_VERSION,
        type: 'themeChanged',
        payload: message.payload && typeof message.payload === 'object'
          ? (message.payload as { theme?: string })
          : null,
      };
    }

    if (message.type !== 'downloadImage' && message.type !== 'shareImage') return null;
    if (typeof message.requestId !== 'string' || !/^[a-zA-Z0-9-]{1,64}$/.test(message.requestId)) return null;
    if (!message.payload || typeof message.payload !== 'object') return null;

    const url = (message.payload as Record<string, unknown>).url;
    if (typeof url !== 'string' || url.length > 4_096) return null;

    return {
      version: BRIDGE_VERSION,
      type: message.type,
      requestId: message.requestId,
      payload: { url },
    };
  } catch {
    return null;
  }
}

function AppShell() {
  const webViewRef = useRef<WebView>(null);
  const mainDocumentUrlRef = useRef(WEB_ORIGIN);
  const initialFinishedRef = useRef(false);
  const [sourceUrl, setSourceUrl] = useState(WEB_ORIGIN);
  const [webViewKey, setWebViewKey] = useState(0);
  const [canGoBack, setCanGoBack] = useState(false);
  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState<LoadError | null>(null);
  const [dark, setDark] = useState(false);

  const finishInitialLoad = useCallback(() => {
    if (initialFinishedRef.current) return;

    initialFinishedRef.current = true;
    SplashScreen.hideAsync().catch(() => undefined);
  }, []);

  useEffect(() => {
    const timeout = setTimeout(() => {
      if (initialFinishedRef.current) return;

      setLoading(false);
      setLoadError({
        title: 'GenAnh chưa tải được',
        message: 'Kiểm tra kết nối mạng rồi thử lại.',
      });
      finishInitialLoad();
    }, LOAD_TIMEOUT_MS);

    return () => clearTimeout(timeout);
  }, [finishInitialLoad]);

  const navigateInsideApp = useCallback((url: string) => {
    mainDocumentUrlRef.current = url;
    setLoadError(null);
    setLoading(true);
    setSourceUrl(url);
    setWebViewKey((key) => key + 1);
  }, []);

  useEffect(() => {
    let active = true;

    const handleDeepLink = (rawUrl: string | null) => {
      if (!active || !rawUrl) return;

      const mapped = mapDeepLink(rawUrl, WEB_ORIGIN);
      if (mapped) navigateInsideApp(mapped);
    };

    Linking.getInitialURL().then(handleDeepLink).catch(() => undefined);
    const subscription = Linking.addEventListener('url', ({ url }) => handleDeepLink(url));

    return () => {
      active = false;
      subscription.remove();
    };
  }, [navigateInsideApp]);

  useEffect(() => {
    const subscription = BackHandler.addEventListener('hardwareBackPress', () => {
      if (!canGoBack) return false;

      webViewRef.current?.goBack();
      return true;
    });

    return () => subscription.remove();
  }, [canGoBack]);

  const openExternalUrl = useCallback(async (url: string) => {
    if (await Linking.canOpenURL(url)) await Linking.openURL(url);
  }, []);

  const handleNavigationRequest = useCallback((request: { url?: string; isTopFrame?: boolean }) => {
    const url = request.url ?? '';
    const disposition = classifyUrl(url, WEB_ORIGIN);

    if (disposition === 'internal') return true;
    if (disposition === 'external' && request.isTopFrame !== false) {
      openExternalUrl(url).catch(() => undefined);
    }

    return false;
  }, [openExternalUrl]);

  const handleOpenWindow = useCallback((event: { nativeEvent: { targetUrl: string } }) => {
    const url = event.nativeEvent.targetUrl;
    const disposition = classifyUrl(url, WEB_ORIGIN);

    if (disposition === 'internal') navigateInsideApp(url);
    else if (disposition === 'external') openExternalUrl(url).catch(() => undefined);
  }, [navigateInsideApp, openExternalUrl]);

  const sendBridgeResult = useCallback((requestId: string, ok: boolean) => {
    const detail = JSON.stringify(JSON.stringify({
      version: BRIDGE_VERSION,
      requestId,
      ok,
      error: ok ? null : 'Không thể tải hoặc chia sẻ ảnh.',
    }));

    webViewRef.current?.injectJavaScript(`(() => {
      window.dispatchEvent(new CustomEvent('genanh:native-result', {
        detail: JSON.parse(${detail}),
      }));
    })();
    true;`);
  }, []);

  const handleMediaRequest = useCallback(async (request: MediaRequest) => {
    const url = resolveBridgeMediaUrl(request.payload.url, WEB_ORIGIN);

    if (!url || !SIGNED_URL_PATTERN.test(new URL(url).pathname)) {
      sendBridgeResult(request.requestId, false);
      return;
    }
    if (!(await Sharing.isAvailableAsync())) {
      sendBridgeResult(request.requestId, false);
      return;
    }

    const directory = new Directory(Paths.cache, 'genanh-media');
    const destination = new File(directory, `${request.requestId}.jpg`);
    let ok = false;

    try {
      directory.create({ idempotent: true, intermediates: true });
      const file = await File.downloadFileAsync(url, destination, { idempotent: true });
      await Sharing.shareAsync(file.uri, {
        dialogTitle: request.type === 'shareImage' ? 'Chia sẻ ảnh GenAnh' : 'Lưu ảnh GenAnh',
        mimeType: 'image/jpeg',
        UTI: 'public.jpeg',
      });
      ok = true;
    } catch {
      ok = false;
    } finally {
      try {
        if (destination.exists) destination.delete();
      } catch {
        // Cache cleanup must not change user-visible result.
      }
    }

    sendBridgeResult(request.requestId, ok);
  }, [sendBridgeResult]);

  const handleMessage = useCallback((event: WebViewMessageEvent) => {
    const request = parseBridgeRequest(event.nativeEvent.data);
    if (!request) return;

    if (request.type === 'themeChanged') {
      setDark(request.payload?.theme === 'dark');
      return;
    }

    handleMediaRequest(request).catch(() => sendBridgeResult(request.requestId, false));
  }, [handleMediaRequest, sendBridgeResult]);

  const handleLoadStart = useCallback((event: { nativeEvent: { url: string } }) => {
    if (classifyUrl(event.nativeEvent.url, WEB_ORIGIN) === 'internal') {
      mainDocumentUrlRef.current = event.nativeEvent.url;
    }
    if (initialFinishedRef.current) return;

    setLoadError(null);
    setLoading(true);
  }, []);

  const handleNavigationStateChange = useCallback((navigation: WebViewNavigation) => {
    if (classifyUrl(navigation.url, WEB_ORIGIN) === 'internal') {
      mainDocumentUrlRef.current = navigation.url;
    }
    setCanGoBack(navigation.canGoBack);
  }, []);

  const handleLoadEnd = useCallback(() => {
    setLoading(false);
    finishInitialLoad();
  }, [finishInitialLoad]);

  const handleLoadError = useCallback((event: { nativeEvent: { url: string; description: string } }) => {
    if (event.nativeEvent.url !== mainDocumentUrlRef.current) return;

    setLoading(false);
    setLoadError({
      title: 'Không thể mở GenAnh',
      message: event.nativeEvent.description || 'Kiểm tra kết nối mạng rồi thử lại.',
    });
    finishInitialLoad();
  }, [finishInitialLoad]);

  const handleHttpError = useCallback((event: { nativeEvent: { url: string; statusCode: number } }) => {
    if (event.nativeEvent.url !== mainDocumentUrlRef.current) return;

    setLoading(false);
    setLoadError({
      title: `GenAnh trả về lỗi ${event.nativeEvent.statusCode}`,
      message: 'Thử tải lại trang sau ít phút.',
    });
    finishInitialLoad();
  }, [finishInitialLoad]);

  const retry = useCallback(() => {
    setLoadError(null);
    setLoading(true);
    webViewRef.current?.reload();
  }, []);

  const backgroundColor = dark ? DARK_BACKGROUND : LIGHT_BACKGROUND;

  return (
    <SafeAreaView style={[styles.safeArea, { backgroundColor }]} edges={['top', 'bottom']}>
      <StatusBar style={dark ? 'light' : 'dark'} />
      <WebView
        key={webViewKey}
        ref={webViewRef}
        style={[styles.webView, { backgroundColor }]}
        source={{ uri: sourceUrl }}
        originWhitelist={WEBVIEW_ORIGIN_WHITELIST}
        javaScriptEnabled
        domStorageEnabled
        cacheEnabled
        incognito={false}
        sharedCookiesEnabled
        thirdPartyCookiesEnabled={false}
        mixedContentMode="never"
        allowFileAccess={false}
        allowFileAccessFromFileURLs={false}
        allowUniversalAccessFromFileURLs={false}
        injectedJavaScriptBeforeContentLoaded={BRIDGE_SCRIPT}
        onMessage={handleMessage}
        onShouldStartLoadWithRequest={handleNavigationRequest}
        onOpenWindow={handleOpenWindow}
        onNavigationStateChange={handleNavigationStateChange}
        onLoadStart={handleLoadStart}
        onLoadEnd={handleLoadEnd}
        onError={handleLoadError}
        onHttpError={handleHttpError}
        onContentProcessDidTerminate={() => webViewRef.current?.reload()}
        onRenderProcessGone={() => webViewRef.current?.reload()}
        allowsBackForwardNavigationGestures
        pullToRefreshEnabled
        applicationNameForUserAgent="GenAnhApp/1"
        webviewDebuggingEnabled={__DEV__}
      />

      {loading && !loadError ? (
        <View style={[styles.loadingOverlay, { backgroundColor }]} accessibilityLiveRegion="polite">
          <ActivityIndicator size="large" color="#7c3aed" />
          <Text style={[styles.loadingText, dark && styles.textDark]}>Đang mở GenAnh…</Text>
        </View>
      ) : null}

      {loadError ? (
        <View style={[styles.errorOverlay, { backgroundColor }]} accessibilityLiveRegion="assertive">
          <View style={[styles.errorCard, dark && styles.errorCardDark]}>
            <Text style={[styles.errorTitle, dark && styles.textDark]}>{loadError.title}</Text>
            <Text style={[styles.errorMessage, dark && styles.errorMessageDark]}>{loadError.message}</Text>
            <Pressable
              accessibilityRole="button"
              accessibilityLabel="Thử tải lại GenAnh"
              onPress={retry}
              style={({ pressed }) => [styles.retryButton, pressed && styles.retryButtonPressed]}
            >
              <Text style={styles.retryButtonText}>Thử lại</Text>
            </Pressable>
          </View>
        </View>
      ) : null}
    </SafeAreaView>
  );
}

export default function App() {
  return (
    <SafeAreaProvider>
      <AppShell />
    </SafeAreaProvider>
  );
}

const styles = StyleSheet.create({
  safeArea: {
    flex: 1,
  },
  webView: {
    flex: 1,
  },
  loadingOverlay: {
    ...StyleSheet.absoluteFill,
    alignItems: 'center',
    justifyContent: 'center',
    gap: 14,
  },
  loadingText: {
    color: '#52525b',
    fontSize: 15,
    fontWeight: '600',
  },
  textDark: {
    color: '#fafafa',
  },
  errorOverlay: {
    ...StyleSheet.absoluteFill,
    alignItems: 'center',
    justifyContent: 'center',
    padding: 24,
  },
  errorCard: {
    width: '100%',
    maxWidth: 420,
    alignItems: 'center',
    gap: 12,
    borderRadius: 24,
    backgroundColor: '#fafafa',
    padding: 28,
    shadowColor: '#18181b',
    shadowOffset: { width: 0, height: 12 },
    shadowOpacity: 0.12,
    shadowRadius: 28,
    elevation: 8,
  },
  errorCardDark: {
    backgroundColor: '#27272a',
  },
  errorTitle: {
    color: '#18181b',
    fontSize: 21,
    fontWeight: '700',
    textAlign: 'center',
  },
  errorMessage: {
    color: '#71717a',
    fontSize: 15,
    lineHeight: 22,
    textAlign: 'center',
  },
  errorMessageDark: {
    color: '#d4d4d8',
  },
  retryButton: {
    minHeight: 48,
    minWidth: 132,
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: 6,
    borderRadius: 14,
    backgroundColor: '#7c3aed',
    paddingHorizontal: 22,
  },
  retryButtonPressed: {
    opacity: 0.82,
  },
  retryButtonText: {
    color: '#ffffff',
    fontSize: 16,
    fontWeight: '700',
  },
});
