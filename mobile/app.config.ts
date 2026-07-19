import type { ConfigContext, ExpoConfig } from 'expo/config';

function requireHttpsOrigin(rawUrl: string): string {
  const url = new URL(rawUrl);

  if (url.protocol !== 'https:' || url.username || url.password || url.pathname !== '/' || url.search || url.hash) {
    throw new Error('EXPO_PUBLIC_WEB_URL must be an exact HTTPS origin.');
  }

  return url.origin;
}

const webUrl = requireHttpsOrigin(process.env.EXPO_PUBLIC_WEB_URL ?? 'https://genanh.com');
const projectId = 'ef67f11f-09c4-4ad1-a12a-c0bd10567661';

export default ({ config }: ConfigContext): ExpoConfig => ({
  ...config,
  name: 'GenAnh',
  slug: 'genanh',
  owner: 'sammoons-bm',
  version: '1.0.0',
  runtimeVersion: { policy: 'appVersion' },
  updates: {
    url: `https://u.expo.dev/${projectId}`,
  },
  orientation: 'portrait',
  scheme: 'genanh',
  icon: './assets/icon.png',
  userInterfaceStyle: 'automatic',
  ios: {
    supportsTablet: true,
    bundleIdentifier: 'com.sammoon.genanh',
    config: {
      usesNonExemptEncryption: false,
    },
    infoPlist: {
      NSCameraUsageDescription: 'GenAnh dùng camera khi bạn chọn chụp ảnh tham chiếu để tạo hoặc chỉnh sửa ảnh.',
      NSPhotoLibraryUsageDescription: 'GenAnh truy cập ảnh bạn chọn làm ảnh tham chiếu để tạo hoặc chỉnh sửa ảnh.',
      CFBundleAllowMixedLocalizations: true,
    },
  },
  android: {
    package: 'com.sammoon.genanh',
    adaptiveIcon: {
      foregroundImage: './assets/adaptive-icon-foreground.png',
      monochromeImage: './assets/adaptive-icon-monochrome.png',
      backgroundColor: '#ffffff',
    },
    permissions: ['android.permission.INTERNET'],
    blockedPermissions: [
      'android.permission.ACCESS_COARSE_LOCATION',
      'android.permission.ACCESS_FINE_LOCATION',
      'android.permission.READ_EXTERNAL_STORAGE',
      'android.permission.RECORD_AUDIO',
      'android.permission.WRITE_EXTERNAL_STORAGE',
    ],
    softwareKeyboardLayoutMode: 'resize',
  },
  plugins: [
    [
      'expo-splash-screen',
      {
        image: './assets/splash-icon.png',
        imageWidth: 260,
        resizeMode: 'contain',
        backgroundColor: '#ffffff',
        dark: {
          image: './assets/splash-icon.png',
          backgroundColor: '#18181b',
        },
      },
    ],
  ],
  extra: {
    webUrl,
    bridgeVersion: 1,
    eas: { projectId },
  },
});
