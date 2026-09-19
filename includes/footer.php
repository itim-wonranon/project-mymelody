</div> <!-- End main-content -->
<?php if (!isset($hide_footer) || !$hide_footer): ?>
    <footer class="footer mt-auto pt-5 pb-4 border-top border-secondary"
        style="background-color: #0b0b0f; border-color: rgba(255,255,255,0.05) !important;">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-8 col-lg-6 text-center mb-4">
                    <h3 class="fw-bold mb-3" style="color: var(--primary-color); font-style: italic; letter-spacing: 1px;">
                        MY MELODY</h3>
                    <p class="text-secondary fs-5 mb-0">แพลตฟอร์มที่เชื่อมโยงระหว่างนักดนตรีอิสระคุณภาพและผู้จ้างงาน
                        สร้างสรรค์ทุกเมโลดี้ให้เป็นโอกาสที่ไม่มีที่สิ้นสุด</p>
                </div>
            </div>
            <hr class="my-4 border-secondary opacity-25">
            <div class="text-center">
                <p class="mb-0 text-secondary small">&copy; <?php echo date('Y'); ?> My Melody. Crafted with <i
                        class="fas fa-heart text-danger"></i> for Musicians.</p>
            </div>
        </div>
    </footer>
<?php endif; ?>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<!-- Bootstrap 5 Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<!-- Custom JS -->
<script src="js/script.js?v=<?php echo time(); ?>"></script>
</body>

</html>