<section class="product-reviews" style="margin-top: 32px;">
    <h2>Đánh giá & Bình luận sản phẩm</h2>

    <?php if (!empty($ratingSummary)): ?>
        <p>
            Điểm trung bình: <strong><?= htmlspecialchars((string) $ratingSummary['avg_rating'], ENT_QUOTES, 'UTF-8') ?> / 5 ★</strong>
            (<?= (int) $ratingSummary['total_reviews'] ?> đánh giá)
        </p>
    <?php endif ?>

    <form method="POST" action="/comments" style="margin-bottom: 24px;">
        <input type="hidden" name="product_id" value="<?= (int) ($product['id'] ?? 0) ?>">
        <div style="margin-bottom: 10px;">
            <label for="rating">Số sao đánh giá:</label>
            <select name="rating" id="rating" required>
                <option value="5">5 sao - Rất hài lòng</option>
                <option value="4">4 sao - Hài lòng</option>
                <option value="3">3 sao - Bình thường</option>
                <option value="2">2 sao - Không hài lòng</option>
                <option value="1">1 sao - Tệ</option>
            </select>
        </div>
        <div style="margin-bottom: 10px;">
            <label for="content">Nội dung bình luận / đánh giá:</label><br>
            <textarea name="content" id="content" rows="4" style="width: 100%; max-width: 600px;" required placeholder="Chia sẻ cảm nhận của bạn về sản phẩm..."></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Gửi đánh giá</button>
    </form>

    <?php if (empty($comments)): ?>
        <p>Chưa có đánh giá nào cho sản phẩm này. Hãy là người đầu tiên đánh giá!</p>
    <?php else: ?>
        <div class="review-list">
            <?php foreach ($comments as $comment): ?>
                <div class="review-item" style="border-bottom: 1px solid #e5e7eb; padding: 12px 0;">
                    <strong><?= htmlspecialchars($comment['user_name'] ?? 'Khách hàng', ENT_QUOTES, 'UTF-8') ?></strong>
                    <span>— <?= (int) ($comment['rating'] ?? 5) ?> / 5 ★</span>
                    <small style="color: #6b7280; margin-left: 8px;">
                        <?= htmlspecialchars($comment['created_at'] ?? $comment['date'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                    </small>
                    <p style="margin-top: 6px;"><?= nl2br(htmlspecialchars($comment['content'] ?? '', ENT_QUOTES, 'UTF-8')) ?></p>
                </div>
            <?php endforeach ?>
        </div>
    <?php endif ?>
</section>
