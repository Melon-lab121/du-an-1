/* Base Shop — JS thuần, không phụ thuộc thư viện */
(() => {
    'use strict';

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const toastEl = document.getElementById('toast');
    let toastTimer;

    function toast(message, isError = false) {
        if (!toastEl) return;
        toastEl.textContent = message;
        toastEl.classList.toggle('error', isError);
        toastEl.classList.add('show');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => toastEl.classList.remove('show'), 2600);
    }

    function updateCartCount(count) {
        document.querySelectorAll('[data-cart-count]').forEach((el) => {
            el.textContent = count;
            el.classList.add('bump');
            setTimeout(() => el.classList.remove('bump'), 250);
        });
    }

    // Thêm vào giỏ bằng AJAX (form có data-ajax-cart). Không có JS thì form vẫn submit bình thường.
    document.addEventListener('submit', async (event) => {
        const form = event.target.closest('form[data-ajax-cart]');
        if (!form) return;
        event.preventDefault();

        const submitter = event.submitter || form.querySelector('[type="submit"]');
        submitter?.classList.add('loading');
        try {
            const formData = new FormData(form, event.submitter);
            if (event.submitter && event.submitter.name && !formData.has(event.submitter.name)) {
                formData.append(event.submitter.name, event.submitter.value);
            }
            const res = await fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf },
                credentials: 'same-origin',
            });
            const data = await res.json().catch(() => ({}));
            if (res.ok && data.ok) {
                updateCartCount(data.count);
                if (data.redirect) {
                    window.location.href = data.redirect;
                    return;
                }
                toast(data.message || 'Đã thêm vào giỏ hàng');
            } else {
                const firstError = data.errors ? Object.values(data.errors)[0]?.[0] : null;
                toast(firstError || data.message || 'Có lỗi xảy ra', true);
            }
        } catch {
            toast('Không kết nối được máy chủ', true);
        } finally {
            submitter?.classList.remove('loading');
        }
    });

    // Nút +/- số lượng
    document.addEventListener('click', (event) => {
        const btn = event.target.closest('[data-qty] [data-step]');
        if (!btn) return;
        const input = btn.closest('[data-qty]').querySelector('input');
        const min = Number(input.min || 0);
        const max = Number(input.max || 999);
        const next = Math.min(max, Math.max(min, Number(input.value || 0) + Number(btn.dataset.step)));
        input.value = next;
        input.dispatchEvent(new Event('change', { bubbles: true }));
    });

    // Xác nhận trước khi xoá / thao tác quan trọng
    document.addEventListener('click', (event) => {
        const el = event.target.closest('[data-confirm]');
        if (el && !window.confirm(el.dataset.confirm)) {
            event.preventDefault();
        }
    });

    // Checkout: chỉ hiện lựa chọn hình thức SePay khi chọn SePay
    const bankCodes = document.querySelector('[data-bank-codes]');
    document.querySelectorAll('[data-payment-radio]').forEach((radio) => {
        radio.addEventListener('change', () => {
            if (bankCodes) bankCodes.hidden = radio.value !== 'sepay' || !radio.checked;
        });
    });

    // Trang sản phẩm có phân loại: hiện giá/tồn kho/SKU của tổ hợp đang chọn, mờ các giá trị đã hết hàng.
    // Chỉ để hiển thị; server vẫn tự tìm và kiểm tra biến thể khi thêm vào giỏ.
    document.querySelectorAll('form[data-variant-picker]').forEach((form) => {
        const variants = JSON.parse(form.querySelector('[data-variant-json]')?.textContent || '[]');
        const radios = Array.from(form.querySelectorAll('input[type="radio"][name^="options["]'));
        const groups = [...new Set(radios.map((r) => r.name))];
        const buttons = form.querySelectorAll('[type="submit"]');
        const qty = form.querySelector('input[name="quantity"]');
        const priceEl = document.querySelector('[data-variant-price]');
        const stockEl = document.querySelector('[data-variant-stock]');
        const skuEl = document.querySelector('[data-variant-sku]');
        const money = (n) => new Intl.NumberFormat('vi-VN').format(n) + ' ₫';
        const selected = () => groups.map((g) => form.querySelector(`input[name="${g}"]:checked`)).filter(Boolean).map((r) => Number(r.value));
        const matches = (v, ids) => ids.every((id) => v.values.includes(id));

        function render() {
            const ids = selected();
            // Gạch mờ giá trị nào ghép với lựa chọn ở thuộc tính KHÁC mà không ra biến thể còn hàng.
            // Không dùng disabled: khách vẫn bấm được để đổi lựa chọn (radio không bỏ chọn được).
            radios.forEach((r) => {
                const others = groups.filter((g) => g !== r.name)
                    .map((g) => form.querySelector(`input[name="${g}"]:checked`)).filter(Boolean).map((x) => Number(x.value));
                r.toggleAttribute('data-soldout', !variants.some((v) => v.stock > 0 && matches(v, [...others, Number(r.value)])));
            });
            if (ids.length !== groups.length) {
                buttons.forEach((b) => b.disabled = false);
                return;
            }
            const v = variants.find((x) => x.values.length === ids.length && matches(x, ids));
            if (!v) {
                stockEl.textContent = 'Tổ hợp này không bán';
                stockEl.className = 'text-danger';
                buttons.forEach((b) => b.disabled = true);
                return;
            }
            priceEl.innerHTML = '<strong>' + money(v.final) + '</strong>' + (v.final < v.price ? '<del>' + money(v.price) + '</del>' : '');
            stockEl.textContent = v.stock > 0 ? 'Còn ' + v.stock + ' sản phẩm' : 'Phân loại này tạm hết hàng';
            stockEl.className = v.stock > 0 ? 'text-success' : 'text-danger';
            if (skuEl) skuEl.textContent = v.sku || '—';
            if (qty) qty.max = Math.max(1, v.stock);
            buttons.forEach((b) => b.disabled = v.stock <= 0);
        }
        radios.forEach((r) => r.addEventListener('change', render));
        render();
    });

    // Chống double-submit form thường
    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (form.matches('[data-ajax-cart]') || event.defaultPrevented) return;
        const button = form.querySelector('[type="submit"]');
        if (button) setTimeout(() => button.classList.add('loading'), 0);
    });

    // Thanh danh mục ghim mới: nút cuộn trái/phải mượt và tự đưa mục active vào vùng nhìn thấy
    const catStrip = document.querySelector('[data-category-strip]');
    const catPrev = document.querySelector('[data-cat-prev]');
    const catNext = document.querySelector('[data-cat-next]');

    if (catStrip) {
        function syncCategoryArrows() {
            const maxScroll = catStrip.scrollWidth - catStrip.clientWidth;
            if (catPrev) catPrev.hidden = catStrip.scrollLeft <= 6;
            if (catNext) catNext.hidden = maxScroll <= 6 || catStrip.scrollLeft >= maxScroll - 6;
        }

        catPrev?.addEventListener('click', () => {
            catStrip.scrollBy({ left: -260, behavior: 'smooth' });
        });
        catNext?.addEventListener('click', () => {
            catStrip.scrollBy({ left: 260, behavior: 'smooth' });
        });

        catStrip.addEventListener('scroll', syncCategoryArrows, { passive: true });
        window.addEventListener('resize', syncCategoryArrows);

        const activeChip = catStrip.querySelector('.category-chip.active');
        if (activeChip) {
            activeChip.scrollIntoView({ inline: 'center', block: 'nearest' });
        }
        syncCategoryArrows();
    }

    // Giỏ hàng & Đặt hàng: chọn voucher từ select option tự động áp dụng / gỡ mã qua AJAX không tải lại trang
    const voucherSelect = document.querySelector('[data-voucher-select]');
    if (voucherSelect) {
        const summaryBox = document.querySelector('[data-voucher-summary]') || document.getElementById('checkout-order-summary');
        const descEl = document.querySelector('[data-voucher-desc]');

        voucherSelect.addEventListener('change', async () => {
            const code = voucherSelect.value.trim();
            const url = code ? voucherSelect.dataset.applyUrl : voucherSelect.dataset.removeUrl;
            if (!url) return;

            const body = new FormData();
            body.append('_token', csrf);
            if (code) body.append('code', code);

            const checkoutForm = voucherSelect.closest('form');
            if (checkoutForm) {
                checkoutForm.querySelectorAll('input[name="selected_keys[]"]').forEach((input) => {
                    if (input.value) body.append('selected_keys[]', input.value);
                });
            }

            voucherSelect.disabled = true;
            try {
                const res = await fetch(url, {
                    method: 'POST',
                    body,
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf },
                    credentials: 'same-origin',
                });
                const data = await res.json().catch(() => ({}));
                if (res.ok && data.success) {
                    if (summaryBox && data.summary_html) {
                        summaryBox.innerHTML = data.summary_html;
                    }
                    if (descEl) {
                        if (data.applied) {
                            descEl.className = 'text-success';
                            descEl.innerHTML = '';
                            const strong = document.createElement('strong');
                            strong.textContent = data.applied.code;
                            descEl.append('✓ Đang áp dụng ', strong, `: ${data.applied.description}`);
                        } else {
                            descEl.className = 'muted';
                            descEl.textContent = '';
                        }
                    }
                    document.dispatchEvent(new CustomEvent('voucher:updated', { detail: data }));
                    toast(data.message || 'Đã cập nhật voucher');
                } else {
                    toast(data.message || 'Không áp dụng được voucher', true);
                    if (!data.applied) voucherSelect.value = '';
                }
            } catch {
                toast('Không kết nối được máy chủ', true);
            } finally {
                voucherSelect.disabled = false;
            }
        });
    }
})();
