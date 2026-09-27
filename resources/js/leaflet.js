import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';

// Leaflet menyusun URL ikon penanda secara relatif terhadap berkas CSS-nya.
// Vite memindahkan CSS itu ke public/build dan memberi nama hash pada gambar,
// jadi tanpa penunjukan ulang ini penanda peta hilang tanpa galat apa pun.
L.Icon.Default.mergeOptions({
    iconUrl: markerIcon,
    iconRetinaUrl: markerIcon2x,
    shadowUrl: markerShadow,
});

window.L = L;
