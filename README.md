# doofinder-prestashop

Plugin that allows to configure the [Doofinder](http://www.doofinder.com) search service in a Prestashop 1.5 store with less effort than configuring it from scratch.

## How to install

The easiest way of installing the plugin is downloading it from our [support page](http://support.doofinder.com). If you want to download it from this page, you can download the latest release from the tags section, but you will have to prepare the module `.zip` file prior to installing it.

If it is the case, there is an included `package.sh` script file (UNIX systems) that will create the package for you. If you are using Windows refer to that script to get hints on how to create the package.

Once you have a `doofinder.zip` package file, please refer to the [Prestashop User Guide](http://doc.prestashop.com/display/PS15/Managing+Modules+and+Themes#ManagingModulesandThemes-Installingmodules) to get instructions on how to install the module.

## Configure Doofinder

The plugin has three configuration sections:

- **The Searchbox:** to configure the plugin search box in top of the page.
- **The Data Feed:** to configure the information displayed in the Doofinder data file.
- **The Doofinder Scripts:** to paste the init scripts for the Doofinder search layer.

### The Searchbox

By default you will use the default Prestashop search box. You only have to remember disabling the AJAX instant search in the Prestashop module to make it work.

If you decide to disable the entire Prestshop default module and use our searchbox you will have to modify the `queryInput` parameter in the `doofinder.options` section on each script, changing that parameter to `#df-searchbox`.

The same must be done if you want to use this plugin as a block in the left or right columns.

![Searchbox configuration](http://f.cl.ly/items/0v1H1c3n3K3p2q44383K/the-searchbox.png)

To enable the search box in the top of the page select `Yes` in the select box and update the `queryInput` value in all the scripts.

You can adjust the position of the box and the width. The box is *absolutely* positioned in the header and the default values are those shown in the previous image.

You can also change the width of the input box.

#### Troubleshooting displaying the module in the frontend

**IMPORTANT:** YOU CAN ONLY HAVE ONE SEARCH BOX. IF YOU USE THE MODULE'S SEARCH BOX AND ADD DOOFINDER TO A COLUMN AN ERROR WILL BE DISPLAYED.

This plugin is designed to optionally display a search box in one of three places:

- **Top of the page** (recommended)
- **Left column**
- **Right column**

This is done by using the so called *hooks*. This plugin uses four main hooks that correspond to the three positions mentioned and a fourth hook that adds a CSS file and the init script to the HTML header of the page.

Said this, you must be sure that the module is attached to two of these hooks, being one of them the HTML header.

In the admin backend go to *Modules > Positions*. You will see a select box labeled *Show:* with a value of *All modules* selected. Select *Doofinder* to display only the information of our interest.

Scroll down to the position labeled *Header of pages* or *displayHeader*. If Doofinder is not there you will have to *transplant* the module there. This position is mandatory to make Doofinder work.

Do the same with the positions labeled *Top of pages* or *Right Column Blocks* or *Left Column Blocks* and transplant the module to the desired position if the module is not there. Remember to use only one of these three positions.

To transplant a module click on the button with a *tick* icon and labeled *Transplant a module*. Check that the selected module is *Doofinder* and in the *Hook into* selector select the desired position. Then click *Save*.

### The Data Feed

Doofinder needs your product information to be read from a data file located in a public web URL. You will find the actual URLs published by this plugin under each of the script text boxes. They will look like:

    http://www.example.com/modules/doofinder/feed.php?lang=es

![Data Feed Settings](http://f.cl.ly/items/0I3Q3W0Y3G271w3j390b/the-data-feed.png)

In the Data Feed section you can configure these parameters:

- **Product Image Size:** The image size to be displayed in the layer from those defined in your store.
- **Product Description Length:** Index the short description or the long one. The latter is recommended.
- **Currency for each active language:** The price of the products will be converted to the selected currency using the internal conversion rates.

You can also force a different currency conversion by passing a `currency` parameter to the feed URL:

	http://www.example.com/modules/doofinder/feed.php?lang=es&currency=USD

The value must be the ISO alpha code for the currency and the currency must be active in your system. If not, then the default active currency will be used instead.

### The Doofinder Scripts

This section contains so many text boxes as languages you have activated in your online store.

In Doofinder you can have multiple search engines for one website but each search engine can index its that in only one language so, if your store has two languages configured and you want to use Doofinder in both languages you will need to create two search engines in the Doofinder site admin pane.

Once you have the init scripts for each of your store languages, you have to paste them in the corresponding text boxes.

![Doofinder Script Configuration](http://f.cl.ly/items/0b3Q3n1d24341Y0M392j/the-script.png)

It is possible that you have to adjust the scripts to match your design preferences. Don't worry, it's a matter of changing some text values.

You can leave blank any of the text boxes. The layer will not be shown for that language.

#### Script sample

The Doofinder script looks like this:

    <script type="text/javascript">
        var doofinder_script ='//d3chj0zb5zcn0g.cloudfront.net/media/js/doofinder-3.latest.min.js';
        (function(d,t){
            var f=d.createElement(t),s=d.getElementsByTagName(t)[0];f.async=1;
                f.src=('https:'==location.protocol?'https:':'http:')+doofinder_script;
                s.parentNode.insertBefore(f,s)}(document,'script')
        );
        if(!doofinder){var doofinder={};}
        doofinder.options = {
            lang: 'en',
            hashid: 'fffff22da41abxxxxxxxxxx35daaaaaa',
            queryInput: '#search_query_top',
            width: 535,
            dleft: -112,
            dtop: 84,
            marginBottom: 0
        }
    </script>

At the end of the script you will see a `doofinder.options` section. Here is where you will have to make adjustments.

The Doofinder layer is attached to a search box. To identify that input control we use a *CSS selector*. In this case the selector is `#search_query_top` that identifies the HTML element with an id attribute with a value of `search_query_top`. It is the default search box in Prestashop.

There are three other parameters you probably will want to customize:

- `width`: The width of the layer. Use a number without quotes around it.
- `dleft`: Is the horizontal displacement of the layer from the point where it is placed automatically. You can use a positive or negative number without quotes around it.
- `dtop`: Is the vertical displacement of the layer from the point where it is placed automatically. You can use a positive or negative number without quotes around it.

If you decide to put the search box included with this plugin for the top of the page you probably will have to adjust these parameters. Remember to do it for each script.