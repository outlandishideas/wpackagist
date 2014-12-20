            <form class="row collapse" method="GET" action="">
                <div class="large-9 columns">
                    <div class="row collapse">
                        <div class="small-12 columns">
                            <input type="search" autofocus name="q" placeholder="Search" value="<?php if (isset($_GET['q'])) echo htmlspecialchars($_GET['q']); ?>">
                        </div>
                    </div>
                </div>
                <div class="large-3 columns">
                    <div class="row collapse">
                        <div class="small-6 columns">
                            <select name="type">
                                <?php foreach (array('any', 'plugin', 'theme') as $option): ?>
                                    <option <?php if (isset($_GET['type']) && $_GET['type'] == $option) echo 'selected' ?> value="<?php echo $option ?>"><?php echo ucfirst($option) ?></option>
                                <?php endforeach ?>
                            </select>
                        </div>
                        <div class="small-6 columns">
                            <input type="submit" value="Search »" class="button postfix">
                        </div>
                    </div>
                </div>
            </form>
