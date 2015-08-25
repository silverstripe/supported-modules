/*
<?php foreach ($modules as $module): ?>
    <tr>
        <td>
            <?php if ($module["github"]): ?>
                <a href="https://github.com/<?php echo $module["github"]; ?>"><?php echo $module["composer"]; ?></a>
            <?php elseif ($module["gitlab"]): ?>
                <a href="https://gitlab.cwp.govt.nz/<?php echo $module["gitlab"]; ?>"><?php echo $module["composer"]; ?></a>
            <?php else: ?>
                <?php echo $module["composer"]; ?>
            <?php endif; ?>
        </td>
        <td><?php echo $module["type"]; ?></td>
        <?php if ($module["github"]): ?>
            <td class="progress first">
                <a href="https://travis-ci.org/<?php echo $module["github"]; ?>">
                    <img src="http://img.shields.io/travis/<?php echo $module["github"]; ?>.svg?style=flat-square" />
                </a>
            </td>
            <td class="progress">
                <img src="http://img.shields.io/scrutinizer/coverage/g/<?php echo $module["github"]; ?>.svg?style=flat-square" />
            </td>
            <td class="progress last">
                <a href="https://scrutinizer-ci.com/g/<?php echo $module["github"]; ?>">
                    <img src="http://img.shields.io/scrutinizer/g/<?php echo $module["github"]; ?>.svg?style=flat-square" />
                </a>
            </td>
        <?php else: ?>
            <td colspan="3">
                Gitlab
            </td>
        <?php endif; ?>
    </tr>
<?php endforeach; ?>
*/

$.ajax({
    "url": "modules.json",
    "dataType": "json"
}).then(function(modules) {
    var rows = [];

    modules.forEach(function(module) {
        var row = "<tr>";

        if (module.github) {
            row += "<td><a href='https://github.com/" + module.github + "'>" + module.composer + "</a></td>";
        } else if (module.gitlab) {
            row += "<td><a href='https://gitlab.cwp.govt.nz/" + module.gitlab + "'>" + module.composer + "</a></td>";
        } else {
            row += "<td>" + module.composer + "</td>";
        }

        row += "<td>" + module.type + "</td>";

        if (module.github) {
            row += "<td class='progress first'><a href='https://travis-ci.org/" + module.github + "'><img src='http://img.shields.io/travis/" + module.github + ".svg?style=flat-square' /></a></td>";
            row += "<td class='progress'><img src='http://img.shields.io/scrutinizer/coverage/g/" + module.github + ".svg?style=flat-square' /></td>";
            row += "<td class='progress last'><a href='https://scrutinizer-ci.com/g/" + module.github + "'><img src='http://img.shields.io/scrutinizer/g/" + module.github + ".svg?style=flat-square' /></a></td>";
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
