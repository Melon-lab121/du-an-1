# Quy trình làm việc nhóm

Tài liệu này là "luật chơi" chung. Mọi thành viên (và mọi công cụ AI mà thành viên dùng) phải tuân theo.
Đọc kèm:

- [docs/CODING_STANDARDS.md](docs/CODING_STANDARDS.md): viết code thế nào, đặt code ở đâu
- [docs/AI_GUIDELINES.md](docs/AI_GUIDELINES.md): dùng AI thế nào cho đúng
- [AGENTS.md](AGENTS.md): hướng dẫn dành riêng cho AI coding agent (Claude Code, Cursor, Copilot, Codex...)

> Nguồn tham khảo chính: [GitHub Flow](https://docs.github.com/en/get-started/using-github/github-flow),
> [Conventional Commits 1.0.0](https://www.conventionalcommits.org/en/v1.0.0/),
> [Google Engineering Practices – Code Review](https://google.github.io/eng-practices/review/),
> [Semantic Versioning 2.0.0](https://semver.org/), [Keep a Changelog](https://keepachangelog.com/).

---

## 1. Vai trò

| Vai trò | Trách nhiệm |
|---|---|
| **Tech lead** (1 người) | Duyệt thay đổi trong `app/Core/`, `database/migrations/` đã merge, `config/`; quyết định thêm thư viện; giữ `main` luôn chạy được |
| **Reviewer** (luân phiên) | Review PR trong vòng **1 ngày làm việc** |
| **Developer** (tất cả) | Làm task được giao, tự test, mở PR, sửa theo review |

Mỗi tính năng có **một người chịu trách nhiệm (owner)**. Dù code do AI sinh ra, owner vẫn là người chịu trách nhiệm.

## 2. Quản lý công việc

- Mọi việc đều bắt đầu từ một **issue** (GitHub Issues / Trello / Jira...). Không có issue thì không code.
- Issue cần có **mục tiêu**, **tiêu chí nghiệm thu** (acceptance criteria) và **phạm vi** (những gì *không* làm).
- Task nên đủ nhỏ để xong trong **1–2 ngày**. Task lớn hơn thì chia nhỏ.
- Tính năng lớn (coupon, đánh giá sản phẩm, vận chuyển...) làm thành **module** trong `modules/`. Như vậy nhiều người làm song song mà không đụng file của nhau.

## 3. Nhánh (GitHub Flow)

```
main  ←── luôn chạy được, được bảo vệ (protected), không ai push trực tiếp
  └── feature/12-coupon-module
  └── fix/27-cart-negative-qty
  └── docs/31-readme-sepay
```

- Đặt tên nhánh: `<loại>/<số-issue>-<mô-tả-ngắn>`, loại thuộc một trong: `feature`, `fix`, `refactor`, `docs`, `test`, `chore`.
- Tách nhánh từ `main` mới nhất. Trước khi mở PR, cập nhật nhánh theo `main`: `git pull --rebase origin main`.
- Mỗi nhánh làm **một việc**. Không gộp "thêm coupon + sửa CSS header + đổi tên biến" vào cùng một nhánh.
- Xoá nhánh sau khi merge.

**Cài đặt bảo vệ `main`** (tech lead làm 1 lần trên GitHub → Settings → Branches):
yêu cầu PR, ít nhất **1 approval**, chặn force-push, yêu cầu nhánh up-to-date trước khi merge.

## 4. Commit (Conventional Commits)

```
<type>(<scope>): <mô tả ngắn, thể mệnh lệnh>

[thân: giải thích VÌ SAO, không lặp lại diff]

[footer: Closes #12]
```

| type | Khi nào |
|---|---|
| `feat` | Thêm tính năng |
| `fix` | Sửa lỗi |
| `refactor` | Đổi cấu trúc, không đổi hành vi |
| `test` | Thêm/sửa test |
| `docs` | Tài liệu |
| `style` | Format, không đổi logic |
| `chore` | Cấu hình, script, việc vặt |
| `perf` | Tối ưu hiệu năng |

`scope` là khu vực bị ảnh hưởng: `cart`, `checkout`, `sepay`, `admin`, `core`, `db`, `module-coupon`...

Ví dụ tốt:
```
feat(checkout): cho phép chọn hình thức SePay trước khi thanh toán
fix(cart): chặn số lượng âm khi cập nhật giỏ hàng

Người dùng sửa input thành -5 thì tổng tiền bị âm. Ép về 0 và xoá dòng.

Closes #27
```

Ví dụ xấu: `update`, `fix bug`, `sửa lỗi`, `final`, `AI code`, `wip`.

- Mô tả commit được viết tiếng Việt, nhưng `type` và `scope` phải viết tiếng Anh như bảng trên.
- Commit nhỏ, mỗi commit một ý và tự chạy được.
- Thay đổi phá tương thích (đổi chữ ký hàm trong `Core`, đổi tên cột...) thì thêm `!`, ví dụ `feat(core)!: ...`, và ghi chú `BREAKING CHANGE:` ở footer.
- **Không bao giờ commit** `.env`, mật khẩu, `SEPAY_SECRET_KEY`, file trong `storage/logs`, `public/uploads`.

## 5. Pull Request

1. Mở PR **sớm** ở dạng *Draft* nếu muốn hỏi ý kiến; chuyển *Ready for review* khi xong.
2. Điền đủ [mẫu PR](.github/pull_request_template.md), bao gồm phần **khai báo dùng AI**.
3. **Kích thước**: nên dưới ~400 dòng thay đổi (không tính file sinh tự động). PR nhỏ được review kỹ hơn và nhanh hơn. PR lớn hãy tách ra.
4. Tự review diff của chính mình trước khi gắn reviewer. Phải giải thích được **từng dòng**.
5. PR chỉ được merge khi:
   - `php tests/run.php` pass (dán kết quả vào PR)
   - đã có ≥ 1 approval; thay đổi `app/Core/` hoặc migration thì phải có approval của tech lead
   - mọi comment đã được trả lời hoặc resolve
6. Merge bằng **Squash and merge**, tiêu đề squash theo Conventional Commits.

## 6. Code review

Theo [Google Engineering Practices](https://google.github.io/eng-practices/review/reviewer/standard.html):
**duyệt PR khi nó chắc chắn làm codebase tốt hơn, dù chưa hoàn hảo.** Mục tiêu là tiến bộ liên tục, không phải code hoàn hảo.

Thứ tự reviewer cần xem:

1. **Thiết kế**: đặt code đúng tầng chưa (xem `docs/CODING_STANDARDS.md` §2)? Có cần làm thành module không?
2. **Đúng chức năng**: có xử lý trường hợp biên không (giỏ rỗng, hết hàng, gửi lại form, IPN đến 2 lần)?
3. **Bảo mật**: dùng `e()`, CSRF, validate, prepared statement, kiểm tra quyền (checklist §7 của CODING_STANDARDS).
4. **Test**: logic mới có test chưa?
5. **Dễ đọc**: tên biến, độ dài hàm, comment giải thích "vì sao".
6. **Style**: phần nào máy kiểm tra được thì không tranh luận.

Cách comment:

- Góp ý vào **code**, không vào **người**. Viết "Hàm này nên tách..." thay vì "Bạn viết sai...".
- Gắn nhãn mức độ:
  - `[Bắt buộc]`: phải sửa mới merge.
  - `[Nên]`: khuyến nghị.
  - `Nit:`: chi tiết nhỏ, tác giả tự quyết.
  - `[Hỏi]`: chưa hiểu, cần giải thích.
- Reviewer được quyền hỏi *"Đoạn này AI viết phải không? Giải thích giúp mình cách nó chạy."* Tác giả không giải thích được thì PR chưa được merge.

## 7. Định nghĩa "Xong" (Definition of Done)

Một task chỉ được coi là xong khi:

- [ ] Đáp ứng đủ tiêu chí nghiệm thu của issue
- [ ] `php tests/run.php` pass; logic nghiệp vụ mới có test
- [ ] Đã tự chạy thử trên trình duyệt (XAMPP), cả trường hợp lỗi
- [ ] Không còn `dd()`, `var_dump`, `console.log`, code comment-out, TODO không có số issue
- [ ] Có migration mới nếu đổi DB (không sửa migration cũ); đã cập nhật `tests/schema.sqlite.sql`
- [ ] Đã cập nhật tài liệu nếu thêm lệnh, cấu hình `.env`, hook hoặc route công khai
- [ ] PR được approve và merge vào `main`

## 8. Database & migration

- **Không bao giờ sửa** file migration đã merge vào `main`; muốn thay đổi thì tạo migration mới.
- Tên file: `YYYY_MM_DD_HHMMSS_mo_ta.sql` (ví dụ `2026_11_20_090000_add_coupon_code_to_orders.sql`).
- Hai người cùng thêm migration thì không sao, vì chúng chạy theo thứ tự tên file. Nhưng **không được hai người cùng sửa một bảng trong cùng một sprint** mà chưa báo nhau.
- Migration của module đặt trong `modules/<Ten>/migrations/`.

## 9. Phát hành

- Gắn tag theo [SemVer](https://semver.org/): `v1.2.0`, trong đó MAJOR = phá tương thích, MINOR = thêm tính năng, PATCH = sửa lỗi.
- Ghi `CHANGELOG.md` theo [Keep a Changelog](https://keepachangelog.com/) với các mục *Added / Changed / Fixed / Security*.

## 10. Giao tiếp

- Hỏi trong kênh chung của nhóm, đừng nhắn riêng. Câu hỏi của bạn có thể giúp cả nhóm.
- Bị kẹt quá **2 giờ** thì hỏi, đừng âm thầm ôm việc.
- Họp nhanh (stand-up) 10 phút. Mỗi người trả lời: hôm qua làm gì, hôm nay làm gì, đang bị chặn bởi gì.
