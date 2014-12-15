            <form class="row collapse" method="GET" action="">
                <div class="large-10 columns">
                    <div class="row collapse">
                        <div class="small-2 columns">
                            <span class="inline prefix">Search database:</span>
                        </div>
                        <div class="small-10 columns">
                            <input type="search" autofocus name="q" placeholder="twentyeleven" value="<?php if (isset($_GET['q'])) echo htmlspecialchars($_GET['q']); ?>">
                        </div>
                    </div>
                </div>
                <div class="large-2 columns">
                    <div class="row collapse">
                        <div class="small-6 columns">
                            <select name="type">
                                <?php foreach (array('any', 'plugin', 'theme') as $option): ?>
                                    <option <?php if ($_GET['type'] == $option) echo 'selected' ?> value="<?php echo $option ?>"><?php echo ucfirst($option) ?></option>
                                <?php endforeach ?>
                            </select>
                        </div>
                        <div class="small-6 columns">
                            <input type="submit" value="Search »" class="button postfix">
                        </div>
                    </div>
                </div>
            </form>
