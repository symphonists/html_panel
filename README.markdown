# HTML Panel Field
 
* Version: 1.2
* Author: Nick Dunn
* Build Date: 2011-02-05
* Requirements: Symphony 2.2 and cURL (not `fsockopen`)

## Purpose
To allow the creation of rich views of data in the Symphony backend, using only Symphony's native tools (Pages, XSLT and Data Sources). To reduce the barrier to entry of embedding of content within the Symphony backend without so that developers do not experience of creating Extensions and system Fields. "So easy a frontend developer could do it!"

## Installation
 
1. Upload the 'html_panel' folder in this archive to your Symphony 'extensions' folder
2. Enable it by selecting the "Field: HTML Panel", choose Enable from the with-selected menu, then click Apply
3. The field will be available in the list when creating a Section


## Configuration

When adding this field to a section you must provide a **URL Expression**. This is the URL of the HTML to display in the backend. This can be  relative such as `/my-page` or absolute e.g. `http://mydomain.com/my-page`. Values from other fields in the entry can be used to build the URL and are included using XSLT syntax:

    /order-summary-panel/{entry/@id}

The available XML to choose from is a full `<entry>` nodeset, as you would normally see through a Data Source. All fields are included in this XML.
	
## Example of use

This is a quick example to show how an HTML Panel field can be used to display an order summary from a one-to-many section relationship between Orders and Order Items.

The Orders section comprises a Name (reference) and Date field:  
![Orders section](http://nick-dunn.co.uk/assets/files/html-panels.1.png)

Entries in the Order Items section store the item name, unit price, quantity and which Order they are assigned to:  
![Order Items section](http://nick-dunn.co.uk/assets/files/html-panels.2.png)

Viewing the Orders section a user sees the normal fields plus an HTML Panel field showing the order summary:  
![Order entry](http://nick-dunn.co.uk/assets/files/html-panels.3.png)

The HTML Panel field is configured to point to a local Symphony page, passing the viewed (Order) entry ID in the URL:  
![Order Summary page snippet](http://nick-dunn.co.uk/assets/files/html-panels.5.png)

The order summary table is actually served from a frontend page with a Data Source attached, filtering Order Entries by the URL Parameter `{$order}`:
![Order Summary page snippet](http://nick-dunn.co.uk/assets/files/html-panels.4.png)

The XSLT for this page simply creates an HTML table and nothing else:  

	<?xml version="1.0" encoding="UTF-8"?>
	<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
	
	<xsl:output method="xml" omit-xml-declaration="yes" encoding="UTF-8" indent="yes" />

	<xsl:template match="/">
	
		<table>
			<thead>
				<tr>
					<th>Item</th>
					<th>Quantity</th>
					<th>Unit Price</th>
					<th>Total</th>
				</tr>
			</thead>
				<tbody>
				<xsl:for-each select="/data/order-items/entry">
					<tr>
						<td><xsl:value-of select="item-name"/></td>
						<td><xsl:value-of select="quantity"/></td>
						<td>£<xsl:value-of select="unit-price"/></td>
						<td>£<xsl:value-of select="number(quantity * unit-price)"/></td>
					</tr>
				</xsl:for-each>
			</tbody>
		</table>
	
	</xsl:template>

	</xsl:stylesheet>

## Styling HTML Panels

The extension provides a single `html-panel.css` which controls global styling of content within HTML Panel fields. Any custom styling should **not** be put in this file. Instead, each HTML Panel instance is given its own unique ID. For example an HTML Panel field named "Order Summary" in a section named "Orders" will render an HTML container:

	<div class="field field-html_panel">
		<label>Order Summary</label>
		<div id="orders_order-summary" class="html-panel">
			...
		</div>
	</div>

You can create styles and behaviour by creating files at the following locations:

	/workspace/html-panel/orders_order-summary.css
	/workspace/html-panel/orders_order-summary.js

If these files exist, they will be automatically added to the `<head>` when the page loads. Your CSS can then target its specific HTML Panel:
	
	#orders_order-summary {
		...
	}

## A word on security

As [michael-e points out](http://symphony-cms.com/discuss/thread/40332/#position-6) the above example is rather insecure if you are actually displaying order information on a public URL. You should filter in your page XSLT on the existence of a `$cookie-username` parameter (your Symphony author username) as a check that the request is authenticated. Similarly you could use the [Login Info event](http://github.com/symphony/workspace/blob/master/events/event.login.php) attached to your page to get more user information from the page XML.

## Other examples

The uses for this technique are far reaching. Some other possible implementations:

* allowing the user to enter a YouTube video URL in a text input field, and embedding it directly with an HTML Panel
* when multiple Images are assigned to an Article, an HTML Panel could display a read-only list of thumbnails with "Edit" links directly to these entries in the Images section (a type of read-only Subsection/Mediathek field)
* embedding Google Charts or Google Maps
* making web service calls to third party application such as Google Analytics, stock/inventory/fulfilment systems etc.