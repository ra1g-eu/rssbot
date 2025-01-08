// Import required modules
import fetch from "node-fetch";
import { JSDOM } from "jsdom";

// Define the endpoint and POST data
const endpoint = "https://www.alza.sk/Services/EShopService.svc/Filter";

const postData = {
    idCategory: 18903273,
    producers: "",
    parameters: [],
    idPrefix: 0,
    prefixType: 0,
    page: 1,
    pageTo: 1,
    availabilityType: 0,
    newsOnly: false,
    commodityWears: [0],
    upperDescriptionStatus: 0,
    branchId: -2,
    sort: 1,
    categoryType: 1,
    searchTerm: "",
    append: false,
    yearFrom: null,
    yearTo: null,
    artistId: null,
    minPrice: -1,
    maxPrice: -1,
    showOnlyActionCommodities: false,
    useRatingThreshold: false,
    showOnlyAlzaPlusCommodities: false,
    callFromParametrizationDialog: false,
    configurationId: 9,
    scroll: 306,
    hash: "#f&cst=0&cud=0&pg=1&pn=1&prod=",
    counter: 1
};

// Define the headers
const headers = {
    "Content-Type": "application/json; charset=utf-8",
    "Accept": "application/json, text/javascript, */*; q=0.01",
    "Accept-Encoding": "gzip, deflate, br",
    "Cache-Control": "no-cache",
    "Referer": "https://www.alza.sk/",
    "Host": "www.alza.sk",
    "Origin": "https://www.alza.sk",
    "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:133.0) Gecko/20100101 Firefox/133.0",
    "Connection": "keep-alive",
    "X-Requested-With": "XMLHttpRequest",
    "Accept-Language": "sk-SK",
    'Content-Length': JSON.stringify(postData).length,
    'Sec-Fetch-Mode': 'cors',
    'Sec-Fetch-Site': 'same-origin',
    'Sec-Fetch-Dest': 'empty'
};

// Function to fetch data
async function fetchData() {
    try {
        // Make the POST request
        const response = await fetch(endpoint, {
            method: "POST",
            headers: headers,
            body: JSON.stringify(postData)
        });

        // Check if the response is okay
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        // Decode the JSON response
        const responseData = await response.json();

        // Extract the HTML content
        const htmlContent = responseData.d?.Boxes;
        if (!htmlContent) {
            throw new Error("No HTML content found in the response.");
        }

        // Parse the HTML using jsdom
        const dom = new JSDOM(htmlContent);
        const document = dom.window.document;

        // Find all product boxes
        const productNodes = document.querySelectorAll("div.box.browsingitem");

        // Initialize an array to store product data
        const products = [];

        // Iterate over each product box
        productNodes.forEach((productNode) => {
            const name = productNode.querySelector("a.name.browsinglink.js-box-link")?.textContent?.trim() || null;
            const link = productNode.querySelector("a.name.browsinglink.js-box-link")?.getAttribute("href") || null;
            const price = productNode.querySelector("span.price-box__price")?.textContent?.trim() || null;
            const availability = productNode.querySelector("span.avlVal.avl0.none")?.textContent?.trim() || null;

            // Add product data to the array
            products.push({ name, link, price, availability });
        });

        // Log the products
        console.log(products);

    } catch (error) {
        console.error("Error fetching data:", error);
    }
}

// Call the function
fetchData();
