/**
 * WordPress Interactivity API store for Interactive Post Search
 */
import { store, getContext, withScope } from '@wordpress/interactivity';

let debounceTimer;

const { state } =  store('interactive-post-search', {
   state: {
        get showMinLengthMessage() {
            const context = getContext();
            return !context.isLoading && 
                   context.searchTerm.length > 0 && 
                   context.searchTerm.length < 3 &&
                   !context.hasSearched;
        },
        get showResultsHeader() {
            const context = getContext();
            return context.hasSearched && 
                   !context.isLoading && 
                   context.results.length > 0;
        },
        get showNoResults() {
            const context = getContext();
            return context.hasSearched && 
                   !context.isLoading && 
                   context.results.length === 0 &&
                   context.searchTerm.length >= 3;
        },
        get isButtonDisabled() {
            const context = getContext();
            return context.isLoading || context.searchTerm.length < 3;
        },
        get resultsHeaderText() {
            const context = getContext();
            const count = context.totalFound;
            return `Found ${count} post${count !== 1 ? 's' : ''}`+ '  Search term: '+ context.searchTerm;
        },
        get ajaxUrl() {
            const context = getContext();
            return context.ajaxUrl;
        }
        
    },
    actions: {
        updateSearchTerm: (event) => {
            const context = getContext();
            context.searchTerm = event.target.value;
             // Get the root element once, while event is fresh
            const rootEl = event.target.closest('[data-wp-interactive]');
            // Auto-search with debounce when user types
            clearTimeout(debounceTimer);
            const { actions } = store('interactive-post-search');
            
            if (context.searchTerm.length >= 3) {
                debounceTimer = setTimeout(  withScope(() => {
                    const { actions } = store('interactive-post-search');
                    actions.performSearch();
                }, 500));
            } else if (context.searchTerm.length === 0) {
                // Clear results when input is cleared
                context.results = [];
                context.hasSearched = false;
                context.totalFound = 0;
            }
          
           
           
        },
        async performSearch() {
           
            const context = getContext();
            console.log(context.ajaxUrl);
            
             // Validate minimum search length
            if (context.searchTerm.length < 3) {
                return;
            }
            
            // Set loading state
            context.isLoading = true;
            
            // Prepare form data for AJAX request
            const formData = new FormData();
            formData.append('action', 'ips_search');
            formData.append('nonce', context.nonce);
            formData.append('search_term', context.searchTerm);
            formData.append('posts_per_page', context.postsPerPage);
            formData.append('post_type', context.postType);  

             try {
                // Make AJAX request to WordPress
                const response = await fetch(context.ajaxUrl, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Update context with search results
                    context.results = data.data.posts;
                    context.totalFound = data.data.found;
                    context.hasSearched = true;
                } else {
                    // Handle unsuccessful response
                    context.results = [];
                    context.totalFound = 0;
                    context.hasSearched = true;
                }
            } catch (error) {
                // Handle network or parsing errors
                console.error('Search error:', error);
                context.results = [];
                context.totalFound = 0;
                context.hasSearched = true;
            } finally {
                // Always clear loading state
                context.isLoading = false;
            }
        
        }
    },
     callbacks: {
        logCounter: () => {
            const { searchTerm } = getContext();
            console.log( 'Counter is ' + searchTerm + ' at ' + new Date() );
        }
    }
});
