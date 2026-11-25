-- Danh mục xoá mềm: deleted_at NULL = còn dùng; có giá trị = nằm trong thùng rác.
ALTER TABLE categories
    ADD COLUMN deleted_at DATETIME NULL AFTER updated_at,
    ADD KEY categories_deleted_index (deleted_at);
