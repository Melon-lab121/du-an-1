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

    // Thu gọn / Mở rộng danh mục trang chủ
    const catGrid = document.querySelector('[data-category-grid]');
    const catToggleBtns = document.querySelectorAll('[data-category-toggle]');
    const catBottomBtn = document.querySelector('[data-category-collapse-bottom]');
    const catFooter = document.querySelector('[data-category-footer]');
    const catSection = document.getElementById('categories-section');

    if (catGrid) {
        const totalCats = catGrid.children.length;

        function setCategoryExpanded(expanded, shouldScroll = false) {
            catGrid.setAttribute('data-expanded', expanded ? 'true' : 'false');

            catToggleBtns.forEach((btn) => {
                btn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
                const textEl = btn.querySelector('.toggle-text');
                const iconEl = btn.querySelector('.toggle-icon');
                if (textEl) {
                    textEl.textContent = expanded ? 'Thu gọn' : `Xem tất cả (${totalCats})`;
                }
                if (iconEl) {
                    iconEl.textContent = expanded ? '▴' : '▾';
                }
            });

            if (catFooter) {
                catFooter.hidden = !expanded;
            }

            if (!expanded && shouldScroll && catSection) {
                catSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }

        catToggleBtns.forEach((btn) => {
            btn.addEventListener('click', () => {
                const isExpanded = catGrid.getAttribute('data-expanded') === 'true';
                setCategoryExpanded(!isExpanded);
            });
        });

        catBottomBtn?.addEventListener('click', () => {
            setCategoryExpanded(false, true);
        });
    }
})();
