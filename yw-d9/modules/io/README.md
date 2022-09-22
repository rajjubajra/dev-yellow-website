# INTRODUCTION

Provides a simple integration with Intersection Observer API to lazy load blocks
and Views for modern browsers. Their contents will be lazy loaded once visible
on the view port. Will degrade gracefully to use bLazy to lazy load AJAX blocks
and Views for old browsers. Just be sure, bLazy library is left enabled.


## REQUIREMENTS
* [Blazy 2.x](https://drupal.org/project/blazy) (>= RC3)
* **block.html.twig** which preserves **attributes** and **{{ content }}**.


## RECOMMENDED
* [Ajaxin](https://drupal.org/project/ajaxin)

  To have decent loading animations integrated with Blazy images and IO AJAX.


## INSTALLATION
Install the module as usual, more info can be found on:

[Installing Drupal 8 Modules](https://drupal.org/node/1897420)


## CONFIGURATION
Enable this module and its dependency, core Views and Blazy modules.

### 1. ENABLING INTERSECTION OBSERVER
* Visit **/admin/config/media/blazy**
* Check **Enable IO API** option
* Fill out the fallback text for blocks under **Extra Settings > IO fallback**.

### 2. AS A BLOCK OBSERVER
* Visit **/admin/structure/block**.
* Edit one of the blocks, or place a new one.
* Check **Lazyload using Intersection Observer**.

### 3. AS A VIEWS OBSERVER
* Visit **/admin/structure/views**.
* Edit one of the views, or create a new one.
* Enable **Use AJAX** option under **Advanced > Other**.
* Choose **Intersection Observer** under **Pager**.


## FEATURES
* Lazy load ajaxified blocks on being intersected.
* Lazy load ajaxified views on being intersected.


## KNOWN ISSUES / LIMITATIONS
* Slick Carousel with asNavFor is not synced. Should be fine without, though.
* Only if any issue with IO **Disconnect** option, try disabling it. With a mix
  of Blazy images, IO block and pager observers, the IO must stand-by and be
  able to watch the next/ subsequent AJAX results. No expensive methods
  executed on being stand-by. Each item will be unobserved once loaded, instead.
* Only works with `block.html.twig` which has variables.content `{{ content }}`
  printed directly. If no such variable, AJAX loses it own trigger (the link).
* If you don't see the option **Lazyload using Intersection Observer**, it means
  it is internally excluded to declutter, for reasons: not worth lazy loading
  such as page title, or crucial content, etc. Or the region can be ajaxified
  entirely instead such as when using Ultimenu 2.x.


### EXCLUDED BLOCKS
* help_block
* local_tasks_block
* node_syndicate_block
* page_title_block
* search_form_block
* system_branding_block
* system_breadcrumb_block
* system_main_block
* system_messages_block
* user_login_block

### EXCLUDED PROVIDERS
* ultimenu
* jumper


### TIPS
* Do not enable IO for **Ultimenu 2.x** regions as it is capable of ajaxifying
  the entire region instead.
* To simulate fallback for old browsers at modern ones, simply disable IO API at
  **/admin/config/media/blazy**. And only clear cache if any issue.


## MAINTAINERS
* [Gaus Surahman](https://drupal.org/user/159062)
* [Committers](https://www.drupal.org/node/3048387/committers)
* CHANGELOG.txt for helpful souls with their patches, suggestions and reports.
* The Views IO pager was inspired by VIS and VLM, due credits to them.


## READ MORE
See the project page on drupal.org:

* [Intersection Observer module](http://drupal.org/project/io)

See the IO docs at:

* https://developer.mozilla.org/en-US/docs/Web/API/Intersection_Observer_API
* https://developers.google.com/web/updates/2016/04/intersectionobserver
