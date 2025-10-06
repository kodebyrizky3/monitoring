<!-- SweetAlert2 CDN: load sekali per layout -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<?php
// Ambil flashdata yang mungkin dipakai
$swal = session()->getFlashdata('swal');   // array: ['icon','title','text',...]
$err  = session()->getFlashdata('error');  // string error umum (mis. login gagal)
$ok   = session()->getFlashdata('success'); // string success umum (opsional)
?>

<?php if ($swal): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const data = <?= json_encode($swal, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>;
  if (!data.icon)  data.icon  = 'success';
  if (!data.title) data.title = 'Berhasil';
  Swal.fire(data);
});
</script>
<?php endif; ?>

<?php if ($err): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
  Swal.fire({
    icon: 'error',
    title: 'Gagal',
    text: <?= json_encode($err, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>,
    confirmButtonText: 'OK'
  });
});
</script>
<?php endif; ?>

<?php if ($ok): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
  Swal.fire({
    icon: 'success',
    title: 'Berhasil',
    text: <?= json_encode($ok, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>,
    timer: 1500,
    showConfirmButton: false,
    timerProgressBar: true
  });
});
</script>
<?php endif; ?>
