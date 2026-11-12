/**
 * Minimal types of Leaflet, which is loaded as the global `L` object.
 *
 * The scripts are not bundled, so the package cannot be imported. Only the parts used by the plugin are declared.
 */

interface LeafletControl {
	addTo(this: LeafletControl, map: LeafletMap): LeafletControl;
	onAdd?(map: LeafletMap): HTMLElement;
}

type LeafletLatLng = [number, number];

interface LeafletLayer {
	addTo(this: LeafletLayer, map: LeafletMap): LeafletLayer;
}

interface LeafletMap {
	fitBounds(
		this: LeafletMap,
		bounds: Array<LeafletLatLng>,
		options?: { maxZoom?: number; padding?: LeafletLatLng }
	): LeafletMap;
}

interface LeafletMarker extends LeafletLayer {
	bindPopup(this: LeafletMarker, content: string): LeafletMarker;
}

declare const L: {
	control(this: void, options?: { position?: string }): LeafletControl;
	divIcon(
		this: void,
		options: {
			className: string;
			html: string;
			iconAnchor: LeafletLatLng;
			iconSize: LeafletLatLng;
			popupAnchor: LeafletLatLng;
		}
	): unknown;
	map(
		this: void,
		element: HTMLElement,
		options?: { scrollWheelZoom?: boolean }
	): LeafletMap;
	marker(
		this: void,
		latLng: LeafletLatLng,
		options?: { icon?: unknown; title?: string }
	): LeafletMarker;
	tileLayer(
		this: void,
		url: string,
		options: { attribution: string; maxZoom: number }
	): LeafletLayer;
};
