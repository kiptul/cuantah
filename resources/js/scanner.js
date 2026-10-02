import { BrowserQRCodeReader } from '@zxing/browser';

/**
 * Pembaca khusus QR, bukan pembaca serba-format.
 *
 * Yang dipindai hanya satu bentuk, dan pembaca serba-format mencoba setiap
 * format pada tiap bingkai kamera: lebih lambat, dan sempat salah membaca
 * potongan gambar lain sebagai format yang bukan QR.
 */
window.CuantahScanner = {
    BrowserQRCodeReader,
};
