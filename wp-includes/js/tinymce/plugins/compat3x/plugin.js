/**
 * plugin.js
 *
 * Copyright, Moxiecode Systems AB
 * Released under LGPL License.
 *
 * License: http://www.tinymce.com/license
 * Contributing: http://www.tinymce.com/contributing
 */

/*global tinymce:true, console:true */

/**
 * This plugin adds missing events form the 4.x API back. Not every event is
 * properly supported but most things should work.
 *
 * Unsupported things:
 *  - No editor.onEvent
 *  - Can't cancel execCommands with beforeExecCommand
 */
(function(tinymce) {
	var reported;

	function log(apiCall) {
		if (!reported && window && window.console) {
			reported = true;
			console.log("Deprecated TinyMCE API call: " + apiCall);
		}
	}

	function Dispatcher(target, newEventName, argsMap, defaultScope) {
		target = target || this;

		if ( ! newEventName ) {
			this.add = this.addToTop = this.remove = this.dispatch = function(){};
			return;
		}
		
		this.add = function(callback, scope) {
			log('<target>.on' + newEventName + ".add(..)");

			// Convert callback({arg1:x, arg2:x}) -> callback(arg1, arg2)
			function patchedEventCallback(e) {
				var callbackArgs = [];

				if (typeof argsMap == "string") {
					argsMap = argsMap.split(" ");
				}

				if (argsMap && typeof argsMap != "function") {
					for (var i = 0; i < argsMap.length; i++) {
						callbackArgs.push(e[argsMap[i]]);
					}
				}

				if (typeof argsMap == "function") {
					callbackArgs = argsMap(newEventName, e, target);
					if (!callbackArgs) {
						return;
					}
				}

				if (!argsMap) {
					callbackArgs = [e];
				}

				callbackArgs.unshift(defaultScope || target);

				if (callback.apply(scope || defaultScope || target, callbackArgs) === false) {
					e.stopImmediatePropagation();
				}
			}

			target.on(newEventName, patchedEventCallback);

			return patchedEventCallback;
		};

		// Not supported to just use add
		this.addToTop = this.add;

		this.remove = function(callback) {
			return target.off(newEventName, callback);
		};

		this.dispatch = function() {
			target.fire(newEventName);

			return true;
		};
	}

	tinymce.util.Dispatcher = Dispatcher;
	tinymce.onBeforeUnload = new Dispatcher(tinymce, "BeforeUnload");
	tinymce.onAddEditor = new Dispatcher(tinymce, "AddEditor", "editor");
	tinymce.onRemoveEditor = new Dispatcher(tinymce, "RemoveEditor", "editor");

	function noop(){}

	tinymce.util.Cookie = {
		get: noop, getHash: noop, remove: noop, set: noop, setHash: noop
	};

	function patchEditor(editor) {
		function patchEditorEvents(oldEventNames, argsMap) {
			tinymce.each(oldEventNames.split(" "), function(oldName) {
				editor["on" + oldName] = new Dispatcher(editor, oldName, argsMap);
			});
		}

		function convertUndoEventArgs(type, event, target) {
			return [
				event.level,
				target
			];
		}

		function filterSelectionEvents(needsSelection) {
			return function(type, e) {
				if ((!e.selection && !needsSelection) || e.selection == needsSelection) {
					return [e];
				}
			};
		}

		if (editor.controlManager) {
			return;
		}

		editor.controlManager = {
			buttons: {},

			setDisabled: function(name, state) {
				log("controlManager.setDisabled(..)");

				if (this.buttons[name]) {
					this.buttons[name].disabled(state);
				}
			},

			setActive: function(name, state) {
				log("controlManager.setActive(..)");

				if (this.buttons[name]) {
					this.buttons[name].active(state);
				}
			}
		};

		patchEditorEvents("PreInit BeforeRenderUI PostRender Load Init Remove Activate Deactivate", "editor");
		patchEditorEvents("Click MouseUp MouseDown DblClick KeyDown KeyUp KeyPress ContextMenu Paste Submit Reset");
		patchEditorEvents("BeforeExecCommand ExecCommand", "command ui value args"); // args.terminate not supported
		patchEditorEvents("PreProcess PostProcess LoadContent SaveContent Change");
		patchEditorEvents("BeforeSetContent BeforeGetContent SetContent GetContent", filterSelectionEvents(false));
		patchEditorEvents("SetProgressState", "state time");
		patchEditorEvents("VisualAid", "element hasVisual");
		patchEditorEvents("Undo Redo", convertUndoEventArgs);

		patchEditorEvents("NodeChange", function(type, e) {
			return [
				editor.controlManager,
				e.element,
				editor.selection.isCollapsed(),
				e
			];
		});

		var originalAddButton = editor.addButton;
		editor.addButton = function(name, settings) {
			var originalOnPostRender;

			function patchedPostRender() {
				editor.controlManager.buttons[name] = this;

				if (originalOnPostRender) {
					return originalOnPostRender.call(this);
				}
			}

			for (var key in settings) {
				if (key.toLowerCase() === "onpostrender") {
					originalOnPostRender = settings[key];
					settings.onPostRender = patchedPostRender;
				}
			}

			if (!originalOnPostRender) {
				settings.onPostRender = patchedPostRender;
			}

			settings.title = tinymce.i18n.translate((editor.settings.language || "en") + "." + settings.title);

			return originalAddButton.call(this, name, settings);
		};

		editor.on('init', function() {
			var undoManager = editor.undoManager, selection = editor.selection;

			undoManager.onUndo = new Dispatcher(editor, "Undo", convertUndoEventArgs, null, undoManager);
			undoManager.onRedo = new Dispatcher(editor, "Redo", convertUndoEventArgs, null, undoManager);
			undoManager.onBeforeAdd = new Dispatcher(editor, "BeforeAddUndo", null, undoManager);
			undoManager.onAdd = new Dispatcher(editor, "AddUndo", null, undoManager);

			selection.onBeforeGetContent = new Dispatcher(editor, "BeforeGetContent", filterSelectionEvents(true), selection);
			selection.onGetContent = new Dispatcher(editor, "GetContent", filterSelectionEvents(true), selection);
			selection.onBeforeSetContent = new Dispatcher(editor, "BeforeSetContent", filterSelectionEvents(true), selection);
			selection.onSetContent = new Dispatcher(editor, "SetContent", filterSelectionEvents(true), selection);
		});

		editor.on('BeforeRenderUI', function() {
			var windowManager = editor.windowManager;

			windowManager.onOpen = new Dispatcher();
			windowManager.onClose = new Dispatcher();
			windowManager.createInstance = function(className, a, b, c, d, e) {
				log("windowManager.createInstance(..)");

				var constr = tinymce.resolve(className);
				return new constr(a, b, c, d, e);
			};
		});
	}

	tinymce.on('SetupEditor', patchEditor);
	tinymce.PluginManager.add("compat3x", patchEditor);

	tinymce.addI18n = function(prefix, o) {
		var I18n = tinymce.util.I18n, each = tinymce.each;

		if (typeof(prefix) == "string" && prefix.indexOf('.') === -1) {
			I18n.add(prefix, o);
			return;
		}

		if (!tinymce.is(prefix, 'string')) {
			each(prefix, function(o, lc) {
				each(o, function(o, g) {
					each(o, function(o, k) {
						if (g === 'common') {
							I18n.data[lc + '.' + k] = o;
						} else {
							I18n.data[lc + '.' + g + '.' + k] = o;
						}
					});
				});
			});
		} else {
			each(o, function(o, k) {
				I18n.data[prefix + '.' + k] = o;
			});
		}
	};
})(tinymce);
