# SkelPHP Db

*NOTE: The Skel framework is an __experimental__ web applications framework that I've created as an exercise in various systems design concepts. While I do intend to use it regularly on personal projects, it was not necessarily intended to be a "production" framework, since I don't ever plan on providing extensive technical support (though I do plan on providing extensive documentation). It should be considered a thought experiment and it should be used at your own risk. Read more about its conceptual foundations at [my website](https://colors.kaelshipman.me/about/this-website).*

This is a database class inspired by the Android Database Helper. It's actually intended to be a sort of combination data abstraction layer and object relational db model, and the idea is that you extend it as a foundation for the data layer in your application.

The central idea behind it is that whenever you want data, you interact with this class (that's the data abstraction part). That data can be in a sqlite database, a mysql database, the filesystem, the cloud, hardcoded, or any combination. Because of this, the actual interface for it is very sparse. Typically, you would use this class to request data declaratively, so the methods that it implements in the end will be very specific to your application's needs.

The only real requirement is that it provide strings, and this is to facilitate localization in the future.

In terms of the concrete implementation, this class has a few useful bells and whistles:

* It provides upgrades and downgrades via PHP code. Rather than managing versioned SQL schema files, you can simply manipulate the database in code blocks associated with transitions from a given version number to a given version number. These transition blocks are written in `upgradeDatabase` and `downgradeDatabase`, which you must implement when you extend the class.
* Manipulating Skel Data Objects. Skel Data Objects are instances of Skel DataClass, which is a class that assumes a standard table/column model. When you extend DataClass, you override the `TABLE_NAME` constant with the name of the primary table the object is associated with. You will also override the `__construct` method and "define" the object's columns using `addDefinedField`. Finally, for extra, or nonstandard, fields, you can override the Db class's `saveExtraFields` method. Currently, this method simply searches the Data Object for any DataCollection objects and feeds them back into `saveObject`. More on all this later....
* Caching support. The last little whistle is caching support. This is hackish right now, and should probably be implemented with a `cache` property instead, but for now, it's a small collection of methods that allow you to implement a cache (or not) however you want. 

## Interactions

* `DataClass` is a Component with defined fields. It is intended to be either fetched from the database using `DataClass::restoreFromData` or created new. It is assumed to be renderable, provided a template is set using `setTemplate` or via the constructor. 
* `DataCollection` is an array of `DataClass` objects which is assumed to be managed in a standard way according to whether or not a linking table is defined on it.
* `Db` knows how to save and delete `DataClass` objects and any `DataCollection` objects associated with them. There is no default query method, since queries are meant to be highly specific to the data, rather than generalized. Typically, an app will extend the `Db` class to create methods for extracting Data Objects.

## Usage

Eventually, this package is intended to be loaded as a composer package. For now, though, because this is still in very active development, I currently use it via a git submodule:

```bash
cd ~/my-website
git submodule add git@github.com:kael-shipman/skelphp-db.git app/dev-src/skelphp/db
```

This allows me to develop it together with the website I'm building with it. For more on the (somewhat awkward and complex) concept of git submodules, see [this page](https://git-scm.com/book/en/v2/Git-Tools-Submodules).

