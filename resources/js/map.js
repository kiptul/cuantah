import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png';
import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';

// Leaflet menyusun alamat gambar penanda dari lokasi berkas CSS-nya
// sendiri, cara yang tidak lagi berlaku setelah aset dibundel dan namanya
// diberi sidik jari. Penghapusan _getIconUrl diperlukan karena kelas
// bawaannya selalu menempelkan imagePath di depan nilai yang kita isi,
// sehingga alamatnya menjadi rangkap dan gambarnya gagal dimuat.
delete L.Icon.Default.prototype._getIconUrl;

L.Icon.Default.mergeOptions({
    iconRetinaUrl: markerIcon2x,
    iconUrl: markerIcon,
    shadowUrl: markerShadow,
});

window.L = L;
