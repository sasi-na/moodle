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

let Input = {

	init: function(name) {
	},


	onChange: function(event, node, input) {
		if (event && event.target) {
			const e = new CustomEvent('propertyChange', { detail: {value : input.value ?? this.value, input: this, origEvent:event} });
			event.currentTarget.dispatchEvent(e);
		}
	},

	renderTemplate: function(name, data) {
		return tmpl("vvveb-input-" + name, data);
	},

	setValue: function(value) {
		if (this.element[0] && value) {
			let input = this.element[0].querySelector('input');

			if (input) {
				input.value = value;
			}
		}
	},

	render: function(name, data) {
		let html = this.renderTemplate(name, data);
		this.element = generateElements(html);

		//bind events
		if (this.events)
		for (let i in this.events) {
			ev = this.events[i][0];
			fun = this[ this.events[i][1] ];
			el = this.events[i][2];

			this.element[0].addEventListener(ev, function (ev, el, fun, target, event) {
			  if (event.target.closest(el)) {
				  //target, event, element, input
				return fun.call(event.target, event, target, this);
			  }
			}.bind(this, ev, el, fun, this.element[0]));
		}

		return this.element[0];
	}
};

let TextInput = { ...Input, ...{

    events: [
        //event, listener, child element
        ["focusout", "onChange", "input"],
	 ],

	init: function(data) {
		return this.render("textinput", data);
	},
  }
}

let TextareaInput = { ...Input, ...{

    events: [
        ["keyup", "onChange", "textarea"],
	 ],

	setValue: function(value) {
		if (this.element[0] && value) {
			let input = this.element[0].querySelector('textarea');

			if (input) {
				input.value = value;
			}
		}
	},

	init: function(data) {
		return this.render("textareainput", data);
	},
  }
}

let CheckboxInput = { ...Input, ...{

    events: [
        ["change", "onCheck", "input"],
	 ],

	onCheck: function(event, node, input) {
		input.value = this.checked;
		return input.onChange.call(this, event, node, input);
	},

	setValue: function(value) {
		if (this.element[0]) {
			let input = this.element[0].querySelector('input');

			if (input) {
				if (value) {
					input.checked = true;
				} else {
					input.checked = false;
				}
			}
		}
	},

	init: function(data) {
		return this.render("checkboxinput", data);
	},
  }
}

let SelectInput = { ...Input, ...{

    events: [
        ["change", "onChange", "select"],
	 ],


	setValue: function(value) {
		if (this.element[0] && value) {
			let input = this.element[0].querySelector('select');

			if (input) {
				input.value = value;
			}
		}
	},

	init: function(data) {
		return this.render("select", data);
	},
  }
}

let IconSelectInput = { ...Input, ...{

    events: [
        ["change", "onChange", "select"],
	 ],


	setValue: function(value) {
		if (this.element[0] && value) {
			let input = this.element[0].querySelector('select');

			if (input) {
				input.value = value;
			}
		}
	},

	init: function(data) {
		return this.render("icon-select", data);
	},
  }
}

let HtmlListSelectInput = { ...Input, ...{

	data:{},
	cache:{},

    events: [
        //["click", "onChange", "li"],
        ["change", "onListChange", "select"],
        ["keyup", "searchElement", "input.search"],
        ["click", "clearSearch", "button.clear-backspace"],
	 ],

	clearSearch : function(event, element, input) {
		let search = element.querySelector("input.search");
		if (search) {
			search.value = "";
			input.searchElement(event, element, input);
		}

		search.dispatchEvent(new KeyboardEvent("keyup", {
			bubbles: true,
			cancelable: true,
		}));
	},


	searchElement : function(event, element, input) {
		searchText = this.value;

		delay(() => {
			element.querySelectorAll("li").forEach((el, i) => {

				if (!searchText || el.title.indexOf(searchText) > -1) {
					el.style.display = '';
				} else {
					el.style.display = 'none';
				}
			});

		}, 500);
	},

	onElementClick: function(event, element, input) {
		let data = input.data;
		let svg = this.closest(data.insertElement);
		let value = svg.outerHTML ?? "<svg></svg>";
		input.value = value;
		let ret = input.onChange.call(this, event, element, input);

		return element;
	},

	onListChange: function(event, element, input) {
		let url = input.data.url.replace('{value}', this.value);
		let elements = element.querySelector(".elements");

		elements.innerHTML = `<div class="p-4"><div class="spinner-border spinner-border-sm" role="status">
		  <span class="visually-hidden">Loading...</span>
		</div> Loading...</div>`;

		//cache ajax requests
		if (input.cache[url] != undefined) {
			elements.innerHTML = input.cache[url];
		} else {
			fetch(url)
			.then((response) => {
				if (!response.ok) { throw new Error(response) }
				return response.text()
			})
			.then((text) => {
					input.cache[url] = text;
					elements.innerHTML = text;
			})
			.catch(error => {
				displayToast("bg-danger", "Error", "Error loading list");
			});
		}
	},

	setValue: function(value) {
		let select = this.element[0].querySelector("select");
		if (value && select) {
			select.value = value;
		}
	},

	init: function(data) {
		this.data = data;
		this.events.push(["click", "onElementClick", data.clickElement]);
		let template = this.render("html-list-select", data);
		let select = template.querySelector("select");
		this.onListChange.call(select, new Event('change'), template, this);
		return template;
	},
  }
}

let LinkInput = { ...TextInput, ...{

    events: [
        ["change", "onChange", "input"],
	 ],
	/*
	setValue: function(value) {
		//value = value.replace(/(?<!\/)www\./, 'https://www.');
		this.element.querySelector('input').value = value;
	},
	*/
	init: function(data) {
		return this.render("textinput", data);
	},
  }
}

let DateInput = { ...TextInput, ...{

    events: [
        ["change", "onChange", "input"],
	 ],

	init: function(data) {
		return this.render("dateinput", data);
	},
  }
}

let RangeInput = { ...Input, ...{

    events: [
        ["change", "onRangeChange", "input"],
	 ],

	onRangeChange: function(event, node, input) {
		this.parentNode.querySelector('input[type=number]').value = this.value;
		this.parentNode.querySelector('input[type=range]').value = this.value;
		return input.onChange.call(this, event, node, input);
	},

	setValue: function(value) {
		this.element[0].querySelector('input[type=number]').value = value;
		this.element[0].querySelector('input[type=range]').value = value;
	},

	init: function(data) {
		return this.render("rangeinput", data);
	},
  }
}

let NumberInput = { ...Input, ...{

    events: [
        ["change", "onChange", "input"],
	 ],

	init: function(data) {
		return this.render("numberinput", data);
	},
  }
}

let CssUnitInput = { ...Input, ...{

	number:0,
	unit:"px",

    events: [
        ["change", "onInputChange", "select"],
        ["change", "onInputChange", "input"],
        ["keyup", "onInputChange", "input"],
	 ],

	onInputChange: function(event, node, input) {
		if (node) {
			let number = node.querySelector("input").value;
			let unit = node.querySelector("select").value;

			if (this.value != "") input[this.name] = this.value;// this.name = unit or number
			if (unit == "") unit = "px";//if unit is not set use default px

			let value = "";
			if (unit == "auto")  {
				node.classList.add("auto");
				value = unit;
			}
			else  {
				node.classList.remove("auto");
				value = number + (unit ? unit : "");
			}

			input.value = value;

			return input.onChange.call(this, event, node, input);
		}
	},

	setValue: function(value) {
		if (value && this.element) {
			let element = this.element[0];
			this.number = parseFloat(value);
			this.unit = value.replace(this.number, '').trim();

			if (this.unit == "auto") element.classList.add("auto");

			element.querySelector("input[type=number]").value = this.number;
			element.querySelector("select").value = this.unit;
		}
	},

	init: function(data) {
		return this.render("cssunitinput", data);
	},
  }
}

let ColorInput = { ...Input, ...{

	 //html5 color input only supports setting values as hex colors even if the picker returns only rgb
	 rgb2hex: function(value) {
		 if (value) {
			 value = value.trim();

			 if (rgb = value.match(/^rgba?[\s+]?\([\s+]?(\d+)[\s+]?,[\s+]?(\d+)[\s+]?,[\s+]?(\d+)[\s+]?/i)) {

				 return (rgb && rgb.length === 4) ? "#" +
				  ("0" + parseInt(rgb[1],10).toString(16)).slice(-2) +
				  ("0" + parseInt(rgb[2],10).toString(16)).slice(-2) +
				  ("0" + parseInt(rgb[3],10).toString(16)).slice(-2) : rgb;
			 }
		}

		 return value;
	},

    events: [
        ["change", "onChange", "input"],
	 ],

	setValue: function(value) {
		if (this.element[0] && value) {
			let input = this.element[0].querySelector('input');

			if (input) {
				input.value = this.rgb2hex(value);
			}
		}
	},

	init: function(data) {
		//if no palette provided use default
		if (!data.palette) {
			data.palette = Vvveb.ColorPalette.getAll();
		}

		return this.render("colorinput", data);
	},
  }
}

let ImageInput = { ...Input, ...{

    events: [
        ["blur", "onChange", "input[type=text]"],
        ["change", "onChange", "input[type=text]"],
        ["onChange", "onUpload", "input[type=file]"],
	 ],

	setValue: function(value) {
		if(typeof value == "undefined" || value == null){
			value = "";
		}

		//don't set blob value to avoid slowing down the page
		if (value.indexOf("data:image") == -1)
		{
			let input = $('input[type="text"]', this.element);
			input.val(value);

			// Trigger change event to ensure onChange is called
			input.trigger('change');
		}
	},

	onUpload: function(event, node) {

		if (this.files && this.files[0]) {
            var reader = new FileReader();
            reader.onload = imageIsLoaded;
            reader.readAsDataURL(this.files[0]);
            //reader.readAsBinaryString(this.files[0]);
            file = this.files[0];
        }

		function imageIsLoaded(e) {

				image = e.target.result;

				event.data.element.trigger('propertyChange', [image, this]);

				//return;//remove this line to enable php upload

				var formData = new FormData();
				formData.append("file", file);

				$.ajax({
					type: "POST",
					url: 'upload.php',//set your server side upload script url
					data: formData,
					processData: false,
					contentType: false,
					success: function (data) {

						//if image is succesfully uploaded set image url
						event.data.element.trigger('propertyChange', [data, this]);

						//update src input
						$('input[type="text"]', event.data.element).val(data);
					},
					error: function (data) {
						alert(data.responseText);
					}
				});
		}
	},

	init: function(data) {

		return this.render("imageinput", data);
	},

  }
}

// Custom Video Input for editable video component
let  VideoInputDefault = { ...Input, ...{

    events: [
        ["blur", "onChange", "input[type=text]"],
        ["change", "onChange", "input[type=text]"],
        ["onChange", "onUpload", "input[type=file]"],
	 ],

	 setValue: function(value) {
        // don't set blob value to avoid slowing down the page
        if (typeof value == "undefined" || value == null) {
            value = "";
        }
        // if (value.indexOf("data:video") == -1) {
            let input = $('input[type="text"]', this.element);
            input.val(value);

            // Trigger change event to ensure onChange is called
            input.trigger('change');
        // }
    },

	onUpload: function(event, node) {

		if (this.files && this.files[0]) {
            var reader = new FileReader();
            reader.onload = imageIsLoaded;
            reader.readAsDataURL(this.files[0]);
            //reader.readAsBinaryString(this.files[0]);
            file = this.files[0];
        }

		function imageIsLoaded(e) {

				image = e.target.result;
				event.data.element.trigger('propertyChange', [image, this]);

				//return;//remove this line to enable php upload

				var formData = new FormData();
				formData.append("file", file);

				$.ajax({
					type: "POST",
					url: 'upload.php',//set your server side upload script url
					data: formData,
					processData: false,
					contentType: false,
					success: function (data) {

						//if image is succesfully uploaded set image url
						event.data.element.trigger('propertyChange', [data, this]);

						//update src input
						$('input[type="text"]', event.data.element).val(data);
					},
					error: function (data) {
						alert(data.responseText);
					}
				});
		}
	},
	onChange: function(e, node) {

		// 'this' is the input element that triggered the event
		let src = this.value || this.getAttribute('value');

		let selectedElement = Vvveb.Builder.selectedEl.get ? Vvveb.Builder.selectedEl.get(0) : Vvveb.Builder.selectedEl;
		delay(() => {
			selectedElement.src = src;
			// $('input[type="text"]', e.data.element).val(src);
			selectedElement.querySelector('source').src = src;
			e.data.element.trigger('propertyChange', [src, this]);
		}, 500);
	},

	init: function(data) {
		return this.render("videoinput", data);
	},

  }
}
// Custom Video Input for non editable video component having mulitple slideslike Edwiser slider
let  VideoInput = { ...Input, ...{

    events: [
        ["blur", "onChange", "input[type=text]"],
        ["change", "onChange", "input[type=text]"],
        ["onChange", "onUpload", "input[type=file]"],
	 ],

	 setValue: function(value) {
        // don't set blob value to avoid slowing down the page
        if (typeof value == "undefined" || value == null) {
            value = "";
        }
        // if (value.indexOf("data:video") == -1) {
            let input = $('input[type="text"]', this.element);
            input.val(value);

            // Trigger change event to ensure onChange is called
            input.trigger('change');
        // }
    },

	onUpload: function(event, node) {

		if (this.files && this.files[0]) {
            var reader = new FileReader();
            reader.onload = imageIsLoaded;
            reader.readAsDataURL(this.files[0]);
            //reader.readAsBinaryString(this.files[0]);
            file = this.files[0];
        }

		function imageIsLoaded(e) {

				image = e.target.result;
				event.data.element.trigger('propertyChange', [image, this]);

				//return;//remove this line to enable php upload

				var formData = new FormData();
				formData.append("file", file);

				$.ajax({
					type: "POST",
					url: 'upload.php',//set your server side upload script url
					data: formData,
					processData: false,
					contentType: false,
					success: function (data) {

						//if image is succesfully uploaded set image url
						event.data.element.trigger('propertyChange', [data, this]);

						//update src input
						$('input[type="text"]', event.data.element).val(data);
					},
					error: function (data) {
						alert(data.responseText);
					}
				});
		}
	},
	onChange: function(e, node) {

		// 'this' is the input element that triggered the event
		let src = this.value || this.getAttribute('value');

		// Call the base Input.onChange to trigger propertyChange event
		// This ensures property-level onChange handlers are called
		// The property system will handle updating the correct element based on property.child selector
		Input.onChange.call(this, e, node, this);

		// Note: We don't manually update the video element here because:
		// 1. The propertyChange event handler in builder.js will update the correct element
		// 2. It uses property.child selector to find the specific element (e.g., `.edw-carousel-item-${i} video source`)
		// 3. Manually updating selectedElement.querySelector('source') would update the first source, not the correct one
	},

	init: function(data) {
		return this.render("videoinput", data);
	},

  }
}

let FileUploadInput = { ...TextInput, ...{

    events: [
        ["focusout", "onChange", "input"],
	 ],

	init: function(data) {
		return this.render("textinput", data);
	},
  }
}

let RadioInput = { ...Input, ...{

	events: [
        ["change", "onChange", "input"],
	 ],

	setValue: function(value) {
		if (this.element[0] && value) {
			let input = this.element[0].querySelector('input');

			if (input) {
				if (value == input.value) {
					input.setAttribute("checked", "true");
					input.checked = true;
				} else {
					input.checked = false;
					input.removeAttribute("checked");
				}

			}
		}
	},

	init: function(data) {
		return this.render("radioinput", data);
	},
  }
}

let RadioButtonInput = { ...RadioInput, ...{

	setValue: function(value) {
		if (this.element[0] && value) {
			let inputs = this.element[0].querySelectorAll('input');
			inputs.forEach((el, i) => {
				if (value == el.value) {
					selected = el;
				} else {
					el.checked = false;
					el.removeAttribute("checked");
				}
			});

			selected.checked = true;
			selected.setAttribute("checked", "checked");
		}
	},

	init: function(data) {
		return this.render("radiobuttoninput", data);
	},
  }
}

let ToggleInput = { ...Input, ...{
	events: [
        ["change", "onToggleChange", "input"],
	 ],

	 onToggleChange: function(event, node, input) {
		const on  = this.getAttribute("data-value-on")  || "";
		const off = this.getAttribute("data-value-off") ?? "";
		input.value = this.checked ? on : off;
		return input.onChange.call(this, event, node, input);
	},

	setValue: function(value) {
		if (this.element[0]) {
			let input = this.element[0].querySelector('input');

			if (input) {
				if (value == input.getAttribute("data-value-on")) {
					input.checked = true;
					input.setAttribute("checked", true);
				} else {
					input.checked = false;
					input.removeAttribute("checked");
				}
			}
		}
	},

	init: function(data) {
		return this.render("toggle", data);
	},
  }
}

let ValueTextInput = { ...TextInput, ...{

    events: [
        ["focusout", "onChange", "input"],
	 ],

	init: function(data) {
		return this.render("textinput", data);
	},
  }
}

let GridLayoutInput = { ...TextInput, ...{

    events: [
        ["focusout", "onChange", "input"],
	 ],

	init: function(data) {
		return this.render("textinput", data);
	},
  }
}

let ProductsInput = { ...TextInput, ...{

    events: [
        ["focusout", "onChange", "input"],
	 ],

	init: function(data) {
		return this.render("textinput", data);
	},
  }
}

let GridInput = { ...Input, ...{


    events: [
        ["change", "onChange", "select" /*'select'*/],
        ["click", "onChange", "button" /*'select'*/],
	 ],


	setValue: function(value) {
		if (this.element[0] && value) {
			let input = this.element[0].querySelector('select');

			if (input) {
				input.value = value;
				input.querySelector("option[selected]").selected = true;
			}
		}
	},

	init: function(data) {
		return this.render("grid", data);
	},
  }
}

let TextValueInput = { ...Input, ...{


    events: [
        ["focusout", "onChange", "input"],
	    ["click", "onChange", "button" /*'select'*/],
	 ],

	setValue: function(value) {
		return false;
	},

	init: function(data) {
		return this.render("textvalue", data);
	},
  }
}

var Edwbuttonwithtext = $.extend({}, Input, {

    events: [
        ["click", "onChange", "button" /*'select'*/],
	 ],


	setValue: function(value) {
		$('button', this.element).val(value);
	},

	init: function(data) {
		return this.render("edwbuttonwithtext", data);
	},

  }
);

let ButtonInput = { ...Input, ...{

    events: [
        ["click", "onChange", "button" /*'select'*/],
	 ],


	setValue: function(value) {
		if (this.element[0] && value) {
			let input = this.element[0].querySelector('button');

			if (input) {
				input.value = value;
			}
		}
	},

	init: function(data) {
		return this.render("button", data);
	},
  }
}

let SectionInput = { ...Input, ...{

    events: [
        //["click", "onChange", "button" /*'select'*/],
	 ],


	setValue: function(value) {
		return false;
	},

	init: function(data) {
		return this.render("sectioninput", data);
	},
  }
}

let ListInput = { ...Input, ...{

    events: [
        ["change", "onChange", "select"],
        ["click", "remove", ".delete-btn"],
        ["click", "add", ".btn-new"],
        ["click", "select", ".section-item"],
	 ],


	remove: function(event, node, input) {
		let sectionItem = this.closest(".section-item");
		let index = [...sectionItem.parentNode.children].indexOf(sectionItem);//sectionItem.index();
		let data = input.data;

		if (data.removeElement) {
			let container = input.node;
			if (data.container) {
				container.querySelector(data.container);
			}
			container.querySelector(data.selector + ":nth-child(" + (index + 1) + ")").remove();
		}
		sectionItem.remove();

		event.action = "remove";
		event.index = index;
		input.onChange(event, node, input, this);
		event.preventDefault();
		return false;
	},

	add: function(event, node, input) {
		let newElement = input.data.newElement ?? false;
		if (newElement) {
			let container = input.node;
			if (data.container) {
				container.querySelector(data.container);
			}
			container.append(generateElements(newElement)[0]);
		}

		event.action = "add";
		input.onChange(event, node, input, this);
		return false;
	},

	select: function(event, node, input) {
		let sectionItem = this.closest(".section-item");
		if (sectionItem.parentNode) {
			let index = [...sectionItem.parentNode.children].indexOf(sectionItem);//sectionItem.index();

		event.action = "select";
		event.index = index;
		input.onChange(event, node, input, this);
		}
		return false;
	},

	setValue: function(value) {
	},

	init: function(data, node) {
		this.component = data.component;
		this.selector = data.selector;
		this.node = node;

		let elements = this.node.querySelectorAll(data.container + " " + this.selector);
		let options = [];

		elements.forEach(function (e, i) {
			let element = e;
			if (data.nameElement) {
				element = element.querySelector(data.nameElement);
			}
			let name = (data.name = "text" ? element.textContent.substr(0, 15) : element.id);
			options.push({
				name: name,
				type: (data.prefix ?? "") + (i + 1) + (data.suffix ?? ""),
			});
		});

		data.options = options;
		data.elements = elements;
		this.data = data;

		return this.render("listinput", data);
	},
  }
}

let AutocompleteInput = { ...Input, ...{

    events: [
        ["autocomplete.change", "onAutocompleteChange", "input"],
	 ],

	onAutocompleteChange: function(event, value, text) {
		input.value = value;
		return this.onChange(event, node);
	},

	init: function(data) {

		this.element = this.render("textinput", data);

		let autocomplete = new Autocomplete({input:this.element.querySelector("input"), url:data.url});

		return this.element;
	}
  }
}

let AutocompleteList = { ...Input, ...{

    events: [
        ["autocompletelist.change", "onAutocompleteChange", "input"],
	 ],

	onAutocompleteChange: function(event, node, input) {
		input.value = event.detail;
		return input.onChange.call(this, event, node, input);
	},

	setValue: function(value) {
		if (this.element[0] && value) {
			let input = this.element[0].querySelector('input');

			if (input) {
				input.dataset.autocompleteList.setValue(value);

			}
		}
	},

	init: function(data) {

		this.element = this.render("textinput", data);

		let autocomplete = new Autocomplete({input:this.element.querySelector("input"), url:data.url});
		$('input', this.element).autocompleteList(data);//using default parameters

		return this.element;
	}
  }
}

let TagsInput = { ...Input, ...{

    events: [
        ["tagsinput.change", "onTagsInputChange", "input"],
	 ],

	onTagsInputChange: function(event, value, text) {
		input.value = value;
		return this.onChange(event, node);
	},

	setValue: function(value) {
		if (this.element[0] && value) {
			let input = this.element[0].querySelector('select');

			if (input) {
				input.dataset.tagsInput.setValue(value);

			}
		}
	},

	init: function(data) {

		this.element = this.render("tagsinput", data);

		$('input', this.element).tagsInput(data);//using default parameters

		return this.element;
	}
  }
}


let NoticeInput = { ...Input, ...{

    events: [
	 ],

	init: function(data) {
		return this.render("noticeinput", data);
	},
  }
}


var LinkButton = $.extend({}, Input, {

	events: [
		["click", "onChange", "button" /*'select'*/],
	],


	setValue: function (value) {
		$('button', this.element).val(value);
	},

	init: function (data) {
		return this.render("linkbutton", data);
	},

});

var LayoutSelectorInput = $.extend({}, Input, {

	events: [
		["change", "onChange", "input"],
	],

	setValue: function (value) {
		if (value && value != "") {
			$('input', this.element).removeAttr('checked');
			var input = $("input[value=" + value + "]", this.element);
			input.attr("checked", "true").prop('checked', true);
		}
	},

	init: function (data) {
		return this.render("layoutselector", data);
	},
	updateOptions: function (options) {
		$.each(options, function (index, value) {
			var ele = '';
			ele += '<label class="epb-ly-sele" title="'+value.title+'">';
			ele += '<input name="selcourselayout" class="epb-ly-sele-input" type="radio" value="'+value.value+'" id="selcourselayout'+index+'"';
			ele += (value.checked)? 'checked="true">' : '>';
			ele += '<img class="epb-ly-sele-img" src="'+value.img+'" alt="'+value.text+'"></label>';

			$('.layoutselector', this.element).append(ele);
		});
	},
	emptyOptions: function(){
		$(this.element).empty();
	}
}
);

var MultiSelectInput = $.extend({}, Input, {

	events: [
		["change", "onChange", "select"],
	],
	updateOptions: function (options) {
		$.each(options, function (index, value) {
			var option = (value.checked) ? ' selected': '';
			$(".multiselect", this.element).append(`<option value="${value.value}" ${option}>${value.text}</option>`);
		});
	},

	setValue: function (value) {
		$('select', this.element).val(value);
	},

	init: function (data) {
		return this.render("multiselect", data);
	},
}
);

var EdwbuttonInput = $.extend({}, Input, {

    events: [
        ["click", "onChange", "button" /*'select'*/],
	 ],


	setValue: function(value) {
		$('button', this.element).val(value);
	},

	init: function(data) {
		return this.render("edwbutton", data);
	},

  }
);

var edwcustomdropdown = $.extend({}, Input, {

	events: [
		["change", "onChange", "input"],
	],

	updateOptions: function(eleid, html) {
		// Use this.element to scope to the specific instance, not a global ID
		$("#" + eleid + " #edwcustomdropdownmenu").append(html);
	},

	setValue: function (value) {
		// $('select', this.element).val(value);
	},

	init: function (data) {
		return this.render("edwcustomdropdown", data);
	},
}
);

var EdwheaderInput = $.extend({}, Input, {

	events: [
		[],
	],

	setValue: function (value) {
		$('select', this.element).val(value);
	},

	init: function (data) {
		return this.render("edwheader", data);
	},
}
);

