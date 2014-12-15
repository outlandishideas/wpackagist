<?php header('Content-Type: application/opensearchdescription+xml'); ?><?xml version="1.0" encoding="UTF-8"?>
<OpenSearchDescription xmlns="http://a9.com/-/spec/opensearch/1.1/">
  <ShortName>Wordpress Packagist</ShortName>
  <Description>Search Wordpress plugins and themes</Description>
  <Url type="text/html" template="//<?php echo $_SERVER['SERVER_NAME'] . str_replace('/opensearch.php', '/', $_SERVER['REQUEST_URI']); ?>?q={searchTerms}"/>
</OpenSearchDescription>