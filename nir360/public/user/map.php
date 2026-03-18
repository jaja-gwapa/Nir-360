<?php
// (Optional) put your session/role check here
// session_start();
// if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') { header("Location: ../login.php"); exit; }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>User Location Pin</title>

  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
  <style>
    #map { height: 520px; width: 100%; }
    .row { margin: 10px 0; }
    input { padding: 8px; width: 220px; }
    button { padding: 9px 14px; cursor: pointer; }
  </style>
</head>
<body>
  <h2>Pin Your Location</h2>

  <div class="row">
    <label>Latitude:</label>
    <input id="lat" type="text" readonly>
    <label>Longitude:</label>
    <input id="lng" type="text" readonly>
    <button id="btnCurrent">Use my current location</button>
  </div>

  <div id="map"></div>

  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script>
    // Default view (you can change this)
    let startLat = 14.5995; // Manila
    let startLng = 120.9842;

    const map = L.map('map').setView([startLat, startLng], 13);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    let marker = null;

    function setMarker(lat, lng) {
      document.getElementById('lat').value = lat;
      document.getElementById('lng').value = lng;

      if (marker) marker.remove();
      marker = L.marker([lat, lng]).addTo(map).bindPopup("Pinned location").openPopup();
    }

    // Click to pin
    map.on('click', function(e) {
      setMarker(e.latlng.lat.toFixed(6), e.latlng.lng.toFixed(6));
    });

    // Use current location (GPS)
    document.getElementById('btnCurrent').addEventListener('click', () => {
      if (!navigator.geolocation) {
        alert("Geolocation not supported.");
        return;
      }
      navigator.geolocation.getCurrentPosition(
        (pos) => {
          const lat = pos.coords.latitude;
          const lng = pos.coords.longitude;
          map.setView([lat, lng], 16);
          setMarker(lat.toFixed(6), lng.toFixed(6));
        },
        () => alert("Unable to get your location. Please allow location permission.")
      );
    });
  </script>
</body>
</html>