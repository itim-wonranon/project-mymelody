</div> <!-- End main-content -->
    <?php if (!isset($hide_footer) || !$hide_footer): ?>
    <footer class="footer mt-auto pt-5 pb-4 border-top border-secondary" style="background-color: #0b0b0f; border-color: rgba(255,255,255,0.05) !important;">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 mb-4 mb-lg-0">
                    <h4 class="fw-bold mb-3" style="color: var(--primary-color); font-style: italic; letter-spacing: 1px;">MY MELODY</h4>
                    <p class="text-secondary small pe-lg-5">แพลตฟอร์มที่เชื่อมโยงระหว่างนักดนตรีอิสระคุณภาพและผู้จ้างงานอย่างมืออาชีพ สร้างสรรค์ทุกเมโลดี้ให้เป็นโอกาสที่ไม่มีที่สิ้นสุด</p>
                    <div class="mt-4">
                        <a href="#" class="btn btn-outline-secondary btn-sm rounded-circle me-2" style="width: 35px; height: 35px; padding: 0; line-height: 33px; display: inline-flex; align-items: center; justify-content: center;"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="btn btn-outline-secondary btn-sm rounded-circle me-2" style="width: 35px; height: 35px; padding: 0; line-height: 33px; display: inline-flex; align-items: center; justify-content: center;"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="btn btn-outline-secondary btn-sm rounded-circle me-2" style="width: 35px; height: 35px; padding: 0; line-height: 33px; display: inline-flex; align-items: center; justify-content: center;"><i class="fab fa-twitter"></i></a>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 mb-4 mb-md-0">
                    <h6 class="text-white fw-bold mb-3">Links</h6>
                    <ul class="list-unstyled">
                        <li><a href="index.php" class="text-secondary text-decoration-none small hover-glow mb-2 d-block">หน้าแรก</a></li>
                        <li><a href="search.php" class="text-secondary text-decoration-none small hover-glow mb-2 d-block">ค้นหานักดนตรี</a></li>
                        <li><a href="feed.php" class="text-secondary text-decoration-none small hover-glow mb-2 d-block">คอมมูนิตี้</a></li>
                    </ul>
                </div>
                <div class="col-lg-2 col-md-4 mb-4 mb-md-0">
                    <h6 class="text-white fw-bold mb-3">Support</h6>
                    <ul class="list-unstyled">
                        <li><a href="#" class="text-secondary text-decoration-none small hover-glow mb-2 d-block">วิธีใช้งาน</a></li>
                        <li><a href="#" class="text-secondary text-decoration-none small hover-glow mb-2 d-block">ข้อกำหนดและเงื่อนไข</a></li>
                        <li><a href="report_issue.php" class="text-secondary text-decoration-none small hover-glow mb-2 d-block">แจ้งปัญหาการใช้งาน</a></li>
                    </ul>
                </div>
                <div class="col-lg-4 col-md-4">
                    <h6 class="text-white fw-bold mb-3">Newsletter</h6>
                    <p class="text-secondary small mb-3">ติดตามข่าวสารและโอกาสงานใหม่ๆ ก่อนใคร</p>
                    <div class="input-group mb-3">
                        <input type="email" class="form-control bg-dark border-secondary text-white small" placeholder="อีเมลของคุณ" style="border-radius: 50px 0 0 50px;">
                        <button class="btn btn-primary px-3" type="button" style="border-radius: 0 50px 50px 0; color: #3b0059; font-weight: bold;">Subscribe</button>
                    </div>
                </div>
            </div>
            <hr class="my-4 border-secondary opacity-25">
            <div class="text-center">
                <p class="mb-0 text-secondary small">&copy; <?php echo date('Y'); ?> My Melody. Crafted with <i class="fas fa-heart text-danger"></i> for Musicians.</p>
            </div>
        </div>
    </footer>
    <?php endif; ?>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <!-- Bootstrap 5 Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Custom JS -->
    <script src="js/script.js?v=<?php echo time(); ?>"></script>
</body>
</html>
