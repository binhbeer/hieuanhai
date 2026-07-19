# GenAnh Mobile

Expo WebView shell cho `https://genanh.com`.

## Chạy local

```bash
npm install
cp .env.example .env.local
npm run check
npx expo start
```

`EXPO_PUBLIC_WEB_URL` phải là exact HTTPS origin. Dùng `https://chinhanh.local` khi kiểm thử local trên thiết bị truy cập được domain và certificate này. Dùng development build để kiểm native scheme, permission, splash và bridge ảnh end-to-end.

## EAS

Project đã liên kết với `@sammoons-bm/genanh` (`ef67f11f-09c4-4ad1-a12a-c0bd10567661`). Chạy EAS CLI từ thư mục `mobile/`:

```bash
npx eas-cli@latest login
npx expo config --type public
npx eas-cli@latest build --profile preview --platform android
npx eas-cli@latest update --channel preview --environment preview --message "Mô tả thay đổi"
```

Không tạo project EAS mới, không commit token hay signing credential. Universal Links/App Links chỉ thêm sau khi có Apple Team ID và Android signing SHA-256 thật.
