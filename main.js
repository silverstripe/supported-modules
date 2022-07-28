var no_status = 'https://raw.githubusercontent.com/silverstripe/supported-modules/gh-pages/gha-no-status.svg';

$.ajax({
    "url": "modules.json",
    "dataType": "json"
}).then(function(modules) {
    var rows = [];

    modules.forEach(function(module) {
        var row = "<tr>";

        if (module.addons) {
            row += "<td><a href='https://addons.silverstripe.org/add-ons/" + module.composer + "'>" + module.composer + "</a></td>";
        } else {
            if (module.github) {
                row += "<td><a href='https://github.com/" + module.github + "'>" + module.composer + "</a></td>";
            } else if (module.gitlab) {
                row += "<td><a href='https://gitlab.cwp.govt.nz/" + module.gitlab + "'>" + module.composer + "</a></td>";
            } else {
                row += "<td>" + module.composer + "</td>";
            }
        }

        if (module.type === "supported-module") {
            row += "<td>" + "Supported module" + "</td>";
        } else {
            row += "<td>" + "Supported dependency" + "</td>";
        }

        if (module.github) {
            row += [
                "<td class='progress first'>",
                    "<a href='https://github.com/" + module.github + "'>",
                        "<img src='" + no_status + "' class='noci' />",
                    "</a>",
                    "<a href='https://github.com/" + module.github + "/actions/workflows/ci.yml' style='visibility:hidden;'>",
                        "<img src='https://github.com/" + module.github + "/actions/workflows/ci.yml/badge.svg' class='ci' />",
                    "</a>",
                "</td>"
            ].join('');
        } else if (module.gitlab) {
            row += "<td colspan='3'>Module on Gitlab</td>";
        } else {
            row += "<td colspan='3'>Module definition incomplete</td>";
        }

        row += "</tr>";

        rows.push(row);
    });

    $("tbody").html(rows.join(""));
});

// this script is to replace the "missing image" placeholder with a "no status" ci badge

var c = 0;
var interval = setInterval(function() {
    var els = document.querySelectorAll('.progress');
    els.forEach(function(el) {
        var noci = el.querySelector('.noci');
        if (!noci) {
            return;
        }
        var ci = el.querySelector('.ci');
        var style = getComputedStyle(ci);
        // ci badge has loaded
        // using 50 because the "missing image" browser placeholder actually has a computed width
        if (style.width.replace('px', '') > 50) {
            el.removeChild(noci.parentNode);
            ci.parentNode.style.removeProperty('visibility');
        }
    });
    // give it 10 seconds to fetch status badges
    if (c++ >= 4 * 10) {
        // delete missing ci elements
        els.forEach(function(el) {
            var noci = el.querySelector('.noci');
            if (!noci) {
                return;
            }
            var ci = el.querySelector('.ci');
            el.removeChild(ci.parentNode);
        });
        clearInterval(interval);
    }
}, 250);
