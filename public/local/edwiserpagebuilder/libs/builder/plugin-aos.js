/*
Copyright 2017 Ziadin Givan

Licensed under the Apache License, Version 2.0 (the "License");
you may not use this file except in compliance with the License.
You may obtain a copy of the License at

   http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License.

https://github.com/givanz/VvvebJs
*/

/*
Animate on scroll
The edited page should include the aos library https://github.com/michalsnik/aos

  <link rel="stylesheet" href="https://unpkg.com/aos@next/dist/aos.css" />

  <script src="https://unpkg.com/aos@next/dist/aos.js"></script>

  <script>
    AOS.init();
  </script>
*/
// Get AOS library base path
function getAOSBasePath() {
	let aosBasePath = '';
	if (Vvveb.baseUrl) {
		// Remove trailing slash and /builder if present, then add /aos/
		aosBasePath = Vvveb.baseUrl.replace(/\/builder\/?$/, '').replace(/\/$/, '') + '/aos/';
	} else {
		// Fallback: construct path from current script location
		const currentScript = document.currentScript || (document.scripts[document.scripts.length - 1]);
		if (currentScript && currentScript.src) {
			const scriptPath = currentScript.src.substring(0, currentScript.src.lastIndexOf('/'));
			aosBasePath = scriptPath.replace(/\/builder\/?$/, '') + '/aos/';
		} else {
			// Last resort: use relative path
			aosBasePath = '../aos/';
		}
	}
	return aosBasePath;
}

// Load AOS library into iframe when needed (for editor preview)
function loadAOSLibrary() {
	const frameHead = Vvveb.Builder.frameHead;
	const frameBody = Vvveb.Builder.frameBody;

	if (!frameHead || !frameBody) {
		return;
	}

	// Check if AOS is already loaded in head
	if (frameHead.querySelector("#aos-css") && frameHead.querySelector("#aos-js")) {
		// AOS already loaded, just reinitialize if needed
		if (Vvveb.Builder.iframe && Vvveb.Builder.iframe.contentWindow && typeof Vvveb.Builder.iframe.contentWindow.AOS !== 'undefined') {
			Vvveb.Builder.iframe.contentWindow.AOS.refresh();
		}
		return;
	}

	const aosBasePath = getAOSBasePath();

	// Create and add CSS link to head (for editor preview)
	let link = document.createElement('link');
	link.href = aosBasePath + 'aos.css';
	link.id = 'aos-css';
	link.rel = 'stylesheet';

	// Create and add JS script to head (for editor preview)
	let lib = document.createElement('script');
	lib.id = 'aos-js';
	lib.type = 'text/javascript';
	lib.src = aosBasePath + 'aos.js';

	// Create initialization script
	let initScript = document.createElement('script');
	initScript.id = 'aos-init';
	initScript.type = 'text/javascript';
	initScript.text = `
		(function() {
			if (typeof AOS !== 'undefined') {
				AOS.init({
					duration: 1000,
					easing: 'ease-in-out',
					once: false,
					mirror: false
				});
			}
		})();
	`;

	// Add noscript fallback for accessibility
	let noscript = document.createElement('noscript');
	noscript.innerHTML = '<style type="text/css">[data-aos] { opacity: 1 !important; transform: translate(0) scale(1) !important; }</style>';

	// Add to head for editor preview
	frameHead.appendChild(link);
	frameHead.appendChild(lib);
	frameHead.appendChild(initScript);
	frameHead.appendChild(noscript);

	// Initialize AOS after library loads
	lib.addEventListener('load', function() {
		if (Vvveb.Builder.iframe && Vvveb.Builder.iframe.contentWindow) {
			const iframeWindow = Vvveb.Builder.iframe.contentWindow;
			if (typeof iframeWindow.AOS !== 'undefined') {
				iframeWindow.AOS.init({
					duration: 1000,
					easing: 'ease-in-out',
					once: false,
					mirror: false
				});
				iframeWindow.AOS.refresh();
			}
		}
	});
}

// Add AOS assets directly to body DOM (for saved pages)
function addAOSToBody() {
	const frameBody = Vvveb.Builder.frameBody;

	if (!frameBody) {
		return;
	}

	// Check if AOS assets are already in body (to avoid duplicates)
	if (frameBody.querySelector("#aos-assets-container")) {
		return; // Already added
	}

	// Check if any elements have data-aos attribute
	if (frameBody.querySelectorAll("[data-aos]").length === 0) {
		return; // No AOS elements, no need to add assets
	}

	const aosBasePath = getAOSBasePath();

	// Create a container div at the start of body to hold AOS assets
	const aosContainer = document.createElement("div");
	aosContainer.id = "aos-assets-container";
	aosContainer.style.display = "none";

	// Add CSS link
	const cssLink = document.createElement("link");
	cssLink.id = "aos-css";
	cssLink.rel = "stylesheet";
	cssLink.href = aosBasePath + "aos.css";
	aosContainer.appendChild(cssLink);

	// Add JS script
	const jsScript = document.createElement("script");
	jsScript.id = "aos-js";
	jsScript.type = "text/javascript";
	jsScript.src = aosBasePath + "aos.js";
	aosContainer.appendChild(jsScript);

	// Add initialization script
	const initScript = document.createElement("script");
	initScript.id = "aos-init";
	initScript.type = "text/javascript";
	initScript.text = `
		(function() {
			if (typeof AOS !== 'undefined') {
				AOS.init({
					duration: 1000,
					easing: 'ease-in-out',
					once: false,
					mirror: false
				});
			}
		})();
	`;

	// Add noscript fallback
	const noscript = document.createElement("noscript");
	noscript.innerHTML = '<style type="text/css">[data-aos] { opacity: 1 !important; transform: translate(0) scale(1) !important; }</style>';

	aosContainer.appendChild(initScript);
	aosContainer.appendChild(noscript);

	// Insert container at the beginning of body
	frameBody.insertBefore(aosContainer, frameBody.firstChild);

	// Initialize AOS after JS loads
	jsScript.addEventListener('load', function() {
		if (Vvveb.Builder.iframe && Vvveb.Builder.iframe.contentWindow) {
			const iframeWindow = Vvveb.Builder.iframe.contentWindow;
			if (typeof iframeWindow.AOS !== 'undefined') {
				iframeWindow.AOS.init({
					duration: 1000,
					easing: 'ease-in-out',
					once: false,
					mirror: false
				});
				iframeWindow.AOS.refresh();
			}
		}
	});
}

// Load AOS when iframe is ready
window.addEventListener("vvveb.iframe.loaded", function() {
	// Add AOS to body if elements with data-aos exist
	addAOSToBody();
	// Also load in head for editor preview
	loadAOSLibrary();
});

// Monitor for data-aos attribute changes using MutationObserver
let aosObserver = null;
window.addEventListener("vvveb.iframe.loaded", function() {
	const frameBody = Vvveb.Builder.frameBody;
	if (frameBody && !aosObserver) {
		aosObserver = new MutationObserver(function(mutations) {
			mutations.forEach(function(mutation) {
				if (mutation.type === 'attributes' && mutation.attributeName === 'data-aos') {
					const target = mutation.target;
					if (target.hasAttribute('data-aos') && target.getAttribute('data-aos')) {
						// Add AOS to body if not already added
						addAOSToBody();
						// Also load in head for editor preview
						loadAOSLibrary();
					}
				}
			});
		});

		aosObserver.observe(frameBody, {
			attributes: true,
			attributeFilter: ['data-aos'],
			subtree: true
		});
	}
});

// Clean aos classes on save
window.addEventListener("vvveb.getHtml.before", function(event) {
	let doc = event.detail;
	doc.querySelectorAll("[data-aos]").forEach(e => e.classList.remove("aos-animate", "aos-init"));
});

window.addEventListener("vvveb.getHtml.after", function(event) {
	let doc = event.detail;
	doc.querySelectorAll("[data-aos]").forEach(e => e.classList.add("aos-animate", "aos-init"));
	// AOS assets are already in body DOM (added via addAOSToBody)
	// No need to inject again - they're already part of the saved HTML
});

// Ignore aos classes for styles
Vvveb.Builder.ignoreClasses = Vvveb.Builder.ignoreClasses.concat(["aos-init", "aos-animate"]);

let aosAnimations = [{
			value: "",
			text: "[none]"
		},{
		    //Fade animations
			optgroup: "Fade animations"
		},{
			value: "fade",
			text: "Fade"
		},{
			value: "fade-up",
			text: "Fade Up"
		},{
			value: "fade-down",
			text: "Fade down"
		},{
			value: "fade-left",
			text: "Fade left"
		},{
			value: "fade-right",
			text: "Fade right"
		},{
			value: "fade-up-right",
			text: "Fade up right"
		},{
			value: "fade-up-left",
			text: "Fade up left"
		},{
			value: "fade-down-right",
			text: "Fade down right"
		},{
			value: "fade-down-left",
			text: "Fade down left"
		},{
			//Flip animations
			optgroup: "Flip animations"
		},{
			value: "flip-up",
			text: "Flip Up"
		},{
			value: "flip-down",
			text: "Flip Down"
		},{
			value: "flip-left",
			text: "Flip left"
		},{
			value: "flip-right",
			text: "Flip right"
		},{
			//Slide animations
			optgroup: "Slide animations"
		},{
			value: "slide-up",
			text: "Slide up"
		},{
			value: "slide-down",
			text: "Slide down"
		},{
			value: "slide-left",
			text: "Slide left"
		},{
			value: "slide-right",
			text: "Slide right"
		},{
			//Zoom animations
			optgroup: "Zoom animations"
		},{
			value: "zoom-in",
			text: "Zoom in"
		},{
			value: "zoom-in-up",
			text: "Zoom in up"
		},{
			value: "zoom-in-down",
			text: "Zoom in down"
		},{
			value: "zoom-in-left",
			text: "Zoom in left"
		},{
			value: "zoom-in-right",
			text: "Zoom in right"
		},{
			value: "zoom-out",
			text: "Zoom out"
		},{
			value: "zoom-out-up",
			text: "Zoom out up"
		},{
			value: "zoom-out-down",
			text: "Zoom out down"
		},{
			value: "zoom-out-left",
			text: "Zoom out left"
		},{
			value: "zoom-out-right",
			text: "Zoom out right"
		}
];

// Create array with all values as "notapplicable" but keeping same text
let aosAnimationsNotApplicable = aosAnimations.map(item => {
	if (item.optgroup) {
		// Keep optgroups as is
		return item;
	} else if (item.value !== undefined) {
		// Keep text same, but set all values to "notapplicable"
		return {
			value: "notapplicable",
			text: item.text
		};
	}
	return item;
});

/*
let aosEasing = [{
			value: "",
			text: "[default]"
		},{
			value: "linear",
			text: "linear"
		},{
			value: "ease",
			text: "ease"
		},{
			value: "ease-out",
			text: "ease-out"
		},{
			value: "ease-in-out",
			text: "ease-in-out"
		},{
			value: "ease-in-back",
			text: "ease-in-back"
		},{
			value: "ease-out-back",
			text: "ease-out-back"
		},{
			value: "ease-in-out-back",
			text: "ease-in-out-back"
		},{
			value: "ease-in-sine",
			text: "ease-in-sine"
		},{
			value: "ease-out-sine",
			text: "ease-out-sine"
		},{
			value: "ease-in-quad",
			text: "ease-in-quad"
		},{
			value: "ease-out-quad",
			text: "ease-out-quad"
		},{
			value: "ease-in-out-quad",
			text: "ease-in-out-quad"
		},{
			value: "ease-in-cubic",
			text: "ease-in-cubic"
		},{
			value: "ease-out-cubic",
			text: "ease-out-cubic"
		},{
			value: "ease-in-out-cubic",
			text: "ease-in-out-cubic"
		},{
			value: "ease-in-quart",
			text: "ease-in-quart"
		},{
			value: "ease-out-quart",
			text: "ease-out-quart"
		},{
			value: "ease-in-out-quart",
			text: "ease-in-out-quart"
		}
];
*/
let ComponentBaseAnimateScroll = {
	 properties: [{
		key: "animate_header",
		inputtype: SectionInput,
		name:false,
		sort: base_sort++,
		section: advanced_section,
		data: {header:"Animate on scroll" + (typeof window._v9 !== 'undefined' && !window._v9 ? "<i class='fa fa-lock font-size-14 epb-pro-locked-primary-color'></i>" : "")},
	},{
        name: "Animation type",
        key: "type",
		htmlAttr: "data-aos",
        sort: base_sort++,
		section: advanced_section,
		inputtype: SelectInput,
		beforeInit: function(element) {
            if (!this.data) this.data = {};
            this.data.disabled = (typeof window._v9 !== 'undefined' && !window._v9);
            this.data.options = (typeof window._v9 !== 'undefined' && !window._v9) ? aosAnimationsNotApplicable : aosAnimations;
        },
		data: {
			options: aosAnimations,
		},
		onChange: function(node, value) {
			node.classList.remove("aos-init","aos-animate");
			if (value == "") {
				node.removeAttribute("data-aos","data-aos-duration","data-aos-delay");
			} else {
				// Add AOS to body if not already added (for saved pages)
				addAOSToBody();
				// Also load in head for editor preview
				loadAOSLibrary();
				delay(() => {
					node.classList.add("aos-init","aos-animate");
					// Refresh AOS to detect new elements
					if (Vvveb.Builder.iframe && Vvveb.Builder.iframe.contentWindow && typeof Vvveb.Builder.iframe.contentWindow.AOS !== 'undefined') {
						Vvveb.Builder.iframe.contentWindow.AOS.refresh();
					}
				}, node.dataset.aosDuration ? node.dataset.aosDuration : 1000);
			}
			return node;
		}
/*	},{
        name: "Animation easing",
        key: "easing",
		htmlAttr: "data-aos-easing",
        sort: base_sort++,
		section: advanced_section,
		inputtype: SelectInput,
		data: {
			options: aosEasing,
		}*/
	},{
        name: "Duration",
        key: "duration",
		htmlAttr: "data-aos-duration",
        sort: base_sort++,
		section: advanced_section,
		inputtype: RangeInput,
		beforeInit: function(element) {
            if (!this.data) this.data = {};
            this.data.disabled = (typeof window._v9 !== 'undefined' && !window._v9);
        },
		data:{
			max: 10000,
			min:0,
			step:100
	   },
	   defaultValue: 1000
	},{
        name: "Delay",
        key: "delay",
		htmlAttr: "data-aos-delay",
        sort: base_sort++,
		section: advanced_section,
		inputtype: RangeInput,
		beforeInit: function(element) {
            if (!this.data) this.data = {};
            this.data.disabled = (typeof window._v9 !== 'undefined' && !window._v9);
        },
		data:{
			max: 10000,
			min:0,
			step:100
		},
		defaultValue: "0"
	},{
        name: "",
        key: "delay",
		htmlAttr: "data-aos-delay",
        sort: base_sort++,
		section: advanced_section,
		inputtype: ButtonInput,
		beforeInit: function(element) {
            if (!this.data) this.data = {};
            this.data.disabled = (typeof window._v9 !== 'undefined' && !window._v9);
        },
		data: {text:"Play animation", icon:"la-play"},
		onChange: function(node, value) {
			node.classList.remove("aos-init","aos-animate");
			delay(() => node.classList.add("aos-init","aos-animate"),
				node.dataset.aosDuration ? node.dataset.aosDuration : 1000);
			return node;
		}
	}]
};

Vvveb.Components.extend("_base", "_base", ComponentBaseAnimateScroll);
