# RBWC

This is a plugin written for WooCommerce and WordPress. It follows that standards (as much as possible) of WooCommerce. This means that if you do not upgrade your WooCommerce plugin from time to time, the plugin for Resurs Bank may become obsolete also.

If you read this text from within the resurs-bank-payment-gateway-for-woocommerce, you should know that the codebase and README content is not entirely the original content.
If you read this text from [this bitbucket repo](https://bitbucket.tornevall.net/projects/WWW/repos/tornevall-networks-resurs-bank-payment-gateway-for-woocommerce), consider it the original base as of july 2020. This might change in future also.
This plugin will however give you a wider support for filters and actions to simplify the "pluggables". There is also an older version alive here [here](https://bitbucket.tornevall.net/projects/WWW/repos/tornevall-networks-resurs-bank-payment-gateway-for-woocommerce/browse/init.php?at=refs%2Fheads%2Fobsolete%2Fv1-old) that was intended to the first new version. This is also reverted.
 
## Import to Resurs Bank Repo 

If the above text wasn't read from Resurs Bank repo and the intentions is/has been to import the content from "RBWC" into Resurs Bank - have no worries. Most of the code is written with those intentions. It should therefore be possible to just copy/paste the structure. Just make sure that the old base is removed before doing this.

### Other Considerations ###

You should also take a look at a few other things too, that are listed below.
 
#### composer.json
 
The package namespace is specifically pointing to  `tornevall/resurs-bank-payment-gateway-for-woocommerce` which you probably want to change. Also take an important note that the branches may get desynched if different developing is being made in the repos. Once you decide to synchronize, you could just add another remote to your forked gitrepo and synch them.

#### readme.txt

The readme.txt contains another head title. Change it.


### Security Considerations

Version 2.x had some flaws, at least one of the to consider quite severe; the payment methods was written as file libraries and the directory structure has to be writable. The imports of those methods was also written dynamically, meaning the directory structure was [globbed](https://www.php.net/manual/en/function.glob.php) into the runtime. If an attacker was aware of this (which is possible by reading the code), arbitrary files could be written into this structure and get executed by the plugin. For this release, all such elements are removed.

## Configuring

WooCommerce has a quite static configuration setup that makes it hard to create new options.


# Notes to self

* json-configurables instead of static content
