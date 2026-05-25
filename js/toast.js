(function (global) {
    "use strict";

    function showToast(message, options) {
        if (!global.Toastify) return;

        var opts = options || {};
        var type = opts.type || "success";
        var duration = typeof opts.duration === "number" ? opts.duration : 2000;
        var colors = {
            success: "linear-gradient(90deg, #1e3c72, #2a5298)",
            error: "linear-gradient(90deg, #e74c3c, #c0392b)",
            warning: "linear-gradient(90deg, #f39c12, #d35400)",
            info: "linear-gradient(90deg, #3498db, #1e3c72)"
        };

        Toastify({
            text: message,
            duration: duration,
            gravity: "top",
            position: "center",
            offset: { y: 20 },
            close: false,
            stopOnFocus: true,
            style: {
                background: colors[type] || colors.success,
                color: "#f7f7f7"
            }
        }).showToast();
    }

    global.showToast = showToast;
})(window);
