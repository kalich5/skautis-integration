/**
 * Draws the maps of the "Map of the units" block with Leaflet.
 *
 * The markers and the map tiles are prepared on the server, see Blocks::render_map().
 */

interface SkautisMapData {
	markers: Array<SkautisMapMarker>;
	tiles: {
		attribution: string;
		logo: boolean;
		maxZoom: number;
		url: string;
	};
}

interface SkautisMapMarker {
	lat: number;
	lng: number;
	popup: string;
	water: boolean;
}

function skautisAddMapyLogo(map: LeafletMap): void {
	const logo = L.control({ position: 'bottomleft' });
	logo.onAdd = (): HTMLElement => {
		const link = document.createElement('a');
		link.href = 'https://mapy.com/';
		link.target = '_blank';
		link.rel = 'noopener noreferrer';
		link.className = 'skautis-unit-map-logo';
		const image = document.createElement('img');
		image.src = 'https://api.mapy.com/img/api/logo.svg';
		image.alt = 'Mapy.com';
		link.appendChild(image);
		return link;
	};
	logo.addTo(map);
}

function skautisDrawMap(element: HTMLElement): void {
	const data = JSON.parse(
		element.dataset['skautisMap'] ?? '{}'
	) as Partial<SkautisMapData>;
	if (data.markers === undefined || data.tiles === undefined) {
		return;
	}

	// Scrolling the page must not zoom the map.
	const map = L.map(element, { scrollWheelZoom: false });
	L.tileLayer(data.tiles.url, {
		attribution: data.tiles.attribution,
		maxZoom: data.tiles.maxZoom,
	}).addTo(map);
	if (data.tiles.logo) {
		skautisAddMapyLogo(map);
	}

	const bounds: Array<LeafletLatLng> = [];
	for (const marker of data.markers) {
		const icon = L.divIcon({
			className: `skautis-unit-map-marker${marker.water ? ' is-water' : ''}`,
			html: '<span></span>',
			iconAnchor: [15, 36],
			iconSize: [30, 36],
			popupAnchor: [0, -32],
		});
		L.marker([marker.lat, marker.lng], { icon })
			.bindPopup(marker.popup)
			.addTo(map);
		bounds.push([marker.lat, marker.lng]);
	}
	map.fitBounds(bounds, { maxZoom: 15, padding: [40, 40] });
}

function skautisDrawMaps(): void {
	document
		.querySelectorAll<HTMLElement>('[data-skautis-map]')
		.forEach(skautisDrawMap);
}

wp.domReady(skautisDrawMaps);
