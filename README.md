# FilterFlex - Dynamic Content Filtering for WordPress

[![FilterFlex Plugin: Use Cases Demo](https://img.youtube.com/vi/bP8EjaLEYKw/hqdefault.jpg)](https://www.youtube.com/watch?v=bP8EjaLEYKw)

FilterFlex is a powerful and user-friendly WordPress plugin that allows you to dynamically modify the output of various standard WordPress elements like post titles, content, excerpts, and category names based on flexible location rules and a visual output builder.

Stop writing custom PHP snippets for every conditional content change! FilterFlex empowers you to:

* Conditionally Add Prefixes/Suffixes: Easily add text or dynamic data before or after titles, content, etc.
* Replace Content: Completely replace an element's output under specific conditions.
* Visual Output Builder: Construct complex output patterns by dragging and dropping "Available Tags" (like {categories}, {author}, {custom_field:your_key}) and adding static text items.
* Location Rules: Define precisely when and where your filters apply using an intuitive rule builder (e.g., "Show if Post Type is 'Product' AND User Role is 'Customer'"). Supports AND/OR logic between rule groups.
* Transformations: Apply simple text transformations like "Search & Replace," "Convert to Uppercase," "Limit Words," etc., to your generated output.
* Live Preview: See the results of your filter configuration in real-time before saving.
* Extensible: Designed with developers in mind, allowing new filterable elements, output tags, and location rule parameters to be added via WordPress filters.

## Key Features:

* Custom Post Type for Filters: Filters are managed as a dedicated CPT for easy organization.
* Intuitive Admin Interface: Seamlessly integrates with the WordPress admin dashboard.
* Dynamic Output Tags: Include post categories, tags, author, date, custom fields, and the original filtered element itself in your output.

## Flexible Location Rules:

* Target by Post Type, Page, Page Template, Post Category, User Role, Page Type (Front Page, Single, Archive, etc.), and more.
* Combine rules with AND/OR logic.
* Priority Control: Define the execution order when multiple filters target the same element.
* Predefined Transformations: Clean up or modify your output with built-in text transformations.
* AJAX-Powered UI: Smooth and responsive experience for managing rules and previewing changes.
* Developer Friendly: Extensible via WordPress action and filter hooks.
* Secure: Built with WordPress security best practices, including nonces and capability checks.

## Use Cases:

* Automatically add "[Featured]" to the titles of posts in a specific category.
* Append a list of categories to post titles on archive pages.
* Display a custom field value after the post content, but only for logged-in users.
* Show a "Staff Pick" badge next to product titles based on a custom field.
* Modify category names in wp_list_categories to include post counts.
* And much more!

## Getting Started:

1. Download and install the plugin.
2. Navigate to "FilterFlex" in your WordPress admin menu.
3. Click "Add New" to create your first filter.
4. Define your Location Rules, build your Output Pattern, and apply Transformations.
5. Save and see your dynamic content changes live!

## License:
GPL-2.0+
