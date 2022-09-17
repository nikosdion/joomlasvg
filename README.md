# SVG Support for Joomla! 4

A simple plugin which adds SVG support to Joomla 4.

[Download](https://github.com/nikosdion/joomlasvg/releases)

## Description

This is a simple plugin which adds SVG as a valid image type in Joomla's MediaHelper. This allows you to preview SVG images in the Media Manager and select SVG files in media fields.

Features:

* Preview SVGs in the Media Manager.
* Select SVGs anywhere you can choose an image, as long as they use Joomla's Media field. This includes article Fields. If that doesn't work for you check if this is overridden by something else, e.g. JCE.

Requirements:

* Joomla 4.0 or later
* PHP 7.2 or later
* Go to your site's backend, Content, Media, Options and add `svg` to the “Allowed Extensions” and “Legal Image Extensions (File Types)” fields.

## FAQ

### What are the minimum requirements?

PHP 7.2. Joomla 4.0.

### What are SVGs again?

SVG stands for Scalable Vector Graphics. It's an XML-based file format for vector images. They can be scaled to any size without an adverse effect in quality.

### Why do I need this plugin?

You don't **need** this plugin – unless you want to use SVGs efficiently in Joomla 4 without spending a lot of money and / or effort. 

Joomla 4's Media Manager can only be configured to allow uploading of SVG files. It cannot show a preview of them and you cannot select them in an image picker. You can only use them in articles, if you manually enter the URL to them. This plugin addresses these shortcomings.

### Aren't SVGs insecure?

Somewhat, less than they used to, but Joomla does check if they are safe when they are being uploaded.

SVGs allow for JavaScript inside them to support things like animation and interactivity. The problem is that JavaScript can also be used for nefarious purposes. That's why we don't allow all but extremely trusted people to upload JavaScript to our site. This is what made SVGs unsafe.

Around 2017 all major browsers implemented a simple security feature. If an SVG file is included in an `<img>` tag they will refuse to execute any scripts. That makes SVGs relatively safe to use. However, it's conceivable that a malicious SVG is uploaded and a Super User is tricked into opening it directly on their browser, executing JavaScript.

Joomla simply checks if there are unsafe SVG features being used and refuses the upload.

A better way would be strict sanitization but Joomla chose not to do that. If an SVG upload fails it's Joomla's problem, not this plugin's problem. We know how to do it right but Joomla doesn't. Simple as that.

### How does it work?

Go to your site's backend, Content, Media, Options and add `svg` to the “Allowed Extensions” and “Legal Image Extensions (File Types)” fields.

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

To put things in perspective, this is the ONLY thing I found in 15 years that absolutely required patching core files to implement. It's also a case of we could have this change in the core but people don't want to take responsibility. 

### I installed the plugin and it broke my site!

Delete the folder `plugins/system/joomlasvg/services`. Go to your site's backend and uninstall this plugin.

### I uninstalled the plugin and my site is still broken!

Obviously, your problem is not this plugin since it makes ZERO permanent changes to your site whatsoever.

### I installed this plugin and I still can't select SVG images!

Have you followed the instructions about the Media component's Options? If not, do it now.

If you still have a problem, you probably have JCE Editor Pro installed. Go to its options and set "JCE File Browser in Image Fields" to No. When that option is enabled JCE overrides Joomla's Media Manager with its own. 

If you'd rather use JCE's media manager instead please do NOT use this plugin here; you don't need it.