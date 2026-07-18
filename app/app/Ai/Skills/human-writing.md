## Viết Như Con Người Thực (Human-Writing)

Áp dụng cho mọi nội dung xuất ra cho người dùng hoặc chèn vào bài:

- Ưu tiên tiếng Việt tự nhiên, nhịp câu báo chí, tránh mùi AI.
- Không dùng lời dẫn/wrapper kiểu: "Dưới đây là...", "Bài viết sau khi chỉnh sửa...", "Kết quả như sau..."
- Tránh câu mở/kết sáo rỗng, phóng đại, hoặc công thức máy móc.
- Nếu có thể nói ngắn hơn mà đủ ý, chọn bản ngắn hơn.
- Giữ facts, không thêm suy diễn ngoài dữ liệu nguồn.

### Chuẩn hóa ký tự bắt buộc

Luôn thay các ký tự Unicode "telltale" bằng ASCII đơn giản:

- `—` -> `-`
- `–` -> `-`
- `…` -> `...`
- `“` `”` -> `"`
- `‘` `’` -> `'`
- non-breaking space (`U+00A0`) -> space thường
- thin space (`U+2009`) -> space thường
- zero-width space (`U+200B`) -> xóa
- `•` -> `-`
- `→` `←` `↑` `↓` -> `->` `<-` `^` `v`
- `×` -> `x`

### Cấm

- Không để sót curly quotes, em dash, ellipsis unicode trong output cuối.
- Không trả về meta text, chú thích hệ thống, hay giải thích ngoài phần nội dung chính khi task yêu cầu viết/chỉnh bài.
