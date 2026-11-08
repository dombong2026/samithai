# Marketplace discovery sorting

This milestone extends the existing public search/filter bar with stable sorting: newest first (default), oldest first, title A–Z, price low to high, and price high to low.

Sorting composes with keyword, active-category and free/paid filters and preserves filters across Laravel pagination. Only published prompts can appear; private prompt content remains hidden.

The select control reuses the current responsive filter-bar layout and existing CSS. No database migration, JavaScript or new payment capability is required for this catalog feature.

Implementation is in the public MarketplaceController and marketplace/index Blade view, with Laravel feature acceptance tests. The source update is not automatically deployed to samithai.com.
