/**
 * JS file for the performance comparison tool.
 *
 * This is a development tool, created for the sole purpose of helping me investigate performance issues
 * and prove the performance impact of significant changes in code.
 * It is provided in the hope that it will be useful to others but is provided without any warranty,
 * without even the implied warranty of merchantability or fitness for a particular purpose.
 * This code is provided under GPLv3 or at your discretion any later version.
 *
 * @package moodle-jmeter-perfcomp
 * @copyright 2012 Sam Hemelryk (blackbirdcreative.co.nz)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
function collapse_pages(Y) {

    if (!Y.one('#pagearray')) {
        return true;
    }

    Y.one('#pagearray').addClass('js');
    var pages = Y.all('#pagearray .pagecontainer');
    var pagelist = Y.Node.create('<div class="pagelist"><h1>Page list</h1></div>');
    pages.each(function(){
        var pagelink = Y.Node.create('<p>'+this.one('.pagetitle').get('innerHTML')+'</p>')
        pagelink.on('click', function(){
            pages.addClass('hidden');
            this.removeClass('hidden');
        }, this);
        pagelist.append(pagelink);
    });
    Y.one('#pagearray').append(pagelist);
    pages.addClass('hidden');
    
    Y.all('.largegraph').each(function(graph){
        graph.on('click', function(e){
            e.halt();
            var lightbox = Y.Node.create('<div id="lightbox"></div>');
            var overlay = Y.Node.create('<div id="overlay"><iframe src='+graph.get('href')+'></iframe><div class="close">Close&nbsp;&nbsp;</div></div>');
            Y.one('body').append(lightbox).append(overlay);
            lightbox.on('click', function(){lightbox.remove();overlay.remove();});
            overlay.on('click', function(){lightbox.remove();overlay.remove();});
        });
    }, this);
    
    var h = self.document.location.hash.substring(1);
    if (h) {
        var found = false;
        pages.each(function(){
            if (!found && this.hasClass(h)) {
                this.removeClass('hidden');
                found = true;
            }
        });
    } else {
        pages.item(0).removeClass('hidden');
    }

}
