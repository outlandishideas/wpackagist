<?php

include __DIR__ . '/searchbar.php';

if (empty($_GET['q'])) {
    echo '<div class="alert-box info">Please enter a search above.</div>';
    return;
}

include dirname(dirname(__DIR__)) . '/src/bootstrap.php';
$dbp = new \Outlandish\Wpackagist\DatabaseProvider();
$db = $dbp->getDb();

$sql = "SELECT * FROM packages WHERE name LIKE :name";
$params = array(':name' => '%'.$_GET['q'].'%', ':order' => $_GET['q'].'%');

if (isset($_GET['type'])) {
    if ($_GET['type'] == 'theme') {
        $params['class'] = 'Outlandish\Wpackagist\Package\Theme';
        $sql .= " AND class_name = :class";
    } elseif ($_GET['type'] == 'plugin') {
        $params['class'] = 'Outlandish\Wpackagist\Package\Plugin';
        $sql .= " AND class_name = :class";
    }
}

$sql .= ' ORDER BY is_active DESC, name LIKE :order DESC, name ASC LIMIT 50';
$query = $db->prepare($sql);

if (!$query->execute($params)) {
    echo '<div class="alert-box error">Database error.</div>';
    return;
}

if (!($row = $query->fetch())) {
    echo '<div class="alert-box warning">No results.</div>';
    return;
}
?>
<table>
    <thead>
        <tr>
            <th width="8%">Type</th>
            <th width="20%">Name</th>
            <th width="18%"><abbr title="Last time a modification was commited to the SVN repository">Last commited</abbr></th>
            <th width="18%"><abbr title="Last time this package was updated in WPackagist's database">Last fetched</abbr></th>
            <th width="32%">Versions</th>
            <th width="4%">Active</th>
        </tr>
    </thead>
    <tbody>
        <?php do { ?>
        <tr>
            <td>
                <?php echo str_replace('Outlandish\Wpackagist\Package\\', '', $row['class_name']); ?>
            </td>
            <td>
                <?php echo htmlspecialchars($row['name']); ?>
            </td>
            <td>
                <?php echo null === $row['last_committed'] ? '<em>null</em>' : $row['last_committed'] ?>
            </td>
            <td>
                <?php echo null === $row['last_fetched'] ? '<em>null</em>' : $row['last_fetched'] ?>
            </td>
            <td>
                <?php
                    if (null !== ($versions = $row['versions'])) {
                        $versions = array_keys(json_decode($versions, true));
                        usort($versions, 'version_compare');
                        echo implode(', ', $versions);
                    } else {
                        echo '<em>null</em>';
                    }
                ?>
            </td>
            <td style="text-align: center">
                <?php if ($row['is_active']): ?>
                    <span style="color: green">✔</span>
                <?php else: ?>
                    <span style="color: red">✘</span>
                <?php endif ?>
            </td>
        </tr>
        <?php } while ($row = $query->fetch()) ?>
    </tbody>
</table>

<div class="alert-box info">
    If a package has no version and/or is not active, please check it is visible on
    <a href="https://wordpress.org/plugins/">wordpress.org</a> before reporting a
    <a href="https://github.com/outlandishideas/wpackagist/issues/new">bug</a>.
</div>