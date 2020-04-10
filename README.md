# SVG Support for Joomla! 3

A simple plugin which adds SVG support to Joomla 3.

[Download](https://github.com/nikosdion/joomlasvg/releases)

## Description

This is a simple plugin which adds SVG support to Joomla 3. The following features are provided:

* **Safely** upload SVGs through the Media Manager (including through the WYSIWYG editor).
* **Safely** upload SVGs through any third party extension which follows Joomla's standards.
* Automatically sanitize SVGs. If that is not possible (e.g. malformed SVG), it fails the upload with an error.
* Preview SVGs in the Media Manager.
* Select SVGs anywhere you can choose an image, as long as they use Joomla's Media field. This includes article Fields. If that doesn't work for you check if this is overridden by something else, e.g. JCE.

## FAQ

### What are the minimum requirements?

PHP 7.1. Joomla 3.9.

### What are SVGs again?

SVG stands for Scalable Vector Graphics. It's an XML-based file format for vector images. They can be scaled to any size without an adverse effect in quality.

### Why do I need this plugin?

You don't **need** this plugin – unless you want to use SVGs efficiently in Joomla 3 without spending a lot of money and / or effort. 

Joomla 3's Media Manager can only be configured to allow uploading of SVG files. It cannot show a preview of them and you cannot select them in an image picker. You can only use them in articles, if you manually enter the URL to them. This plugin addresses these shortcoming.

### Aren't SVGs insecure?

Somewhat, less than they used to, but we do sanitize them to make them safe.

SVGs allow for JavaScript inside them to support things like animation and interactivity. The problem is that JavaScript can also be used for nefarious purposes. That's why we don't allow all but extremely trusted people to upload JavaScript to our site. This is what made SVGs unsafe.

Around 2017 all major browsers implemented a simple security feature. If an SVG file is included in an `<img>` tag they will refuse to execute any scripts. That makes SVGs relatively safe to use. However, it's conceivable that a malicious SVG is uploaded and a Super User is tricked into opening it directly on their browser, executing JavaScript.

The solution to that is SVG sanitization using a strict whitelist. That's a fancy way to say that if something isn't explicitly allowed to be present in the SVG file it is automatically removed from the file. This takes a potentially unsafe SVG and renders it safe. That's part of what this plugin does.

Moreover, this plugin also removes "external references" i.e. any URL present in the SVG file. This protects you from privacy issues on top of security issues.

### How does it work?

Install the package. Go to Extensions, Plugins. Enable the "System - SVG Support for Joomla!" plugin. Now you can use SVGs.

### No, really, HOW does it work – on a technical level?

Adding SVG support to Joomla requires three things:

* Modifying MediaModelList. There list of image extensions is hardcoded in the PHP code. Moreover, there is no error handling when retrieving the image size and type which also needs to be patched.
* Modifying Joomla\CMS\Helper\MediaHelper. This tells Joomla if a file is an image and the list of file extensions is hardcoded in the PHP code as well. It's also different than the one in MediaModelList because why not? Joomla isn't known for internal consistency. Moreover, we need to patch the canUpload method to sanitize the SVGs on upload, removing all kinds of JavaScript and other nastiness that can result in a security vulnerability. We use the same library as the WordPress Safe SVG plugin.
* Modifying the Media Manager options. This is to allow Joomla to accept SVG files as acceptable file types.

Since modifying the code code directly is a bad idea (core hacks make it impossible to update your site) the approach I used is in-memory patching. The raw PHP code is loaded in memory and patched. The resulting file is written to an in-memory buffer through PHP streams and then loaded from there. Since the patched class is in memory Joomla won't try to reload it from disk. Therefore it is using our patched code.

### Isn't this a core hack?

Technically? Yes. It modifies core code to achieve its goal. There is no other way because of all the things that are hardcoded in Joomla and date back to Joomla 1.5 or even 1.0.

Practically? No. You can still update Joomla without fearing that you will lose SVG support. That's why this plugin is doing in-memory patching instead of modifying core files on disk.

To make the point clearer. If you disable the plugin the Joomla core code is no longer modified. If you enable (publish) the plugin the Joomla core code is modified, but ONLY in memory. That's a super safe way to do it.

### What you're doing is insane!

I find pleasure in doing things that are, um, “unique” in their execution. More so when I'm told it can't be done. If I get to help people doing what I enjoy most, all the better!

### This must have taken forever to write

About two hours. Oh, you were talking about the code, not the README! Sorry, the code only took me just over an hour.

### Why is this not in the Joomla Extensions Directory (JED)?

The JED does not allow extensions which modify core code. Granted, this rule was written with permanent file modifications in mind but the way it's worded would also apply on this plugin as well. 

Even if the wording wasn't the way it is, I still believe that the JED should reject this plugin because it sets a bad example for other third party developers who don't have my experience with core code and haven't found the once-in-a-decade use case where modifying the core code is necessary AND the modification will not even be considered by the Joomla project for the remaining lifetime of the Joomla 3 series (more on that later).

To put things in perspective, this is the ONLY thing I found in 15 years that absolutely required patching core files to implement. It's also the only case where the core change is rejected not because of technical reasons but because there's a self-proclaimed feature freeze in the Joomla 3 series. Everything else I've done (including things Joomla wasn't designed to do, like Two Factor Authentication BEFORE Joomla merged my PR for that feature) ranged from straightforward to isnanely convoluted – I'll give you that – but it _could_ be done without patching core files. 

### Why did you not submit a PR to Joomla instead?

I did submit a PR to Joomla but it got rejected, like many similar PRs about SVGs before it. In fact, I was told that the Joomla project will NOT consider adding SVG support to Joomla 3 and that adding SVG support is a very complicated task. That's what prompted writing this plugin.

### I installed the plugin and it broke my site!

Delete the file `plugins/system/joomlasvg/joomlasvg.php`. Go to your site's backend and uninstall this plugin.

### I uninstalled the plugin and my site is still broken!

Obviously, your problem is not this plugin since it makes ZERO permanent changes to your site whatsoever.

### I installed this plugin and I still can't select SVG images!

You probably have JCE Editor Pro installed. Go to its options and set "JCE File Browser in Image Fields" to No. When that option is enabled JCE overrides Joomla's Media Manager with its own. 

If you'd rather use JCE's media manager instead please do NOT use this plugin here; you don't need it. JCE is a kick-ass editor and I wholeheartedly recommend using its paid version.

I'm not being paid by Ryan to write this; I just **love** JCE. This plugin here is for anyone who can't or doesn't want to use JCE Pro on their sites.

### Can you also add support for WebP / WebM / whatever?

While I technically can, I don't really want to.

### Can I buy you a beverage?

No, thank you :) If you desperately want to spend some money please donate to any cause that supports children or animals in need. Or do something kind for a stranger. Or reduce your fossil fuel usage. Or all of the above. Anything that helps leave the world a better place for our children is worth more than all the gold in the world.