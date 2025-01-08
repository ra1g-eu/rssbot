import json
import pandas as pd
import matplotlib.pyplot as plt
import argparse

# Function to filter GPUs based on name and price interval
def filter_data(data, name_filter, min_price, max_price):
    gpu_data = []
    for gpu, details in data.items():
        if any(name_filter.lower() in gpu.lower() for name_filter in name_filter):  # Filter based on name
            for date, info in details.items():
                if date not in ["link", "name"]:
                    price = float(info["price"].replace(',', '.'))
                    if min_price <= price <= max_price:  # Filter based on price interval
                        gpu_data.append({
                            "GPU": gpu,
                            "Date": date,
                            "Price": price,
                            "Availability": info["availability"]
                        })
    return gpu_data

# Setup argparse
def setup_arguments():
    parser = argparse.ArgumentParser(description="Filter and visualize GPU price trends.")
    parser.add_argument('--name_filter', nargs='+', default=["geforce"],
                        help="List of GPU name substrings to filter (case insensitive).")
    parser.add_argument('--min_price', type=float, default=0,
                        help="Minimum price for the price range.")
    parser.add_argument('--max_price', type=float, default=float('inf'),
                        help="Maximum price for the price range.")
    return parser.parse_args()

# Main function to run the script
def main():
    # Parse command line arguments
    args = setup_arguments()

    # Load JSON data
    with open('gpus.json', 'r') as file:
        data = json.load(file)

    # Filter the data based on name and price interval
    filtered_data = filter_data(data, args.name_filter, args.min_price, args.max_price)

    # Convert to DataFrame
    df = pd.DataFrame(filtered_data)
    if df.empty:
        print("No data matching the given filters.")
        return

    df['Date'] = pd.to_datetime(df['Date'], format="%d-%m-%Y")

    # Plot price trends
    plt.figure(figsize=(12, 6))
    for gpu, group in df.groupby("GPU"):
        plt.plot(group["Date"], group["Price"], label=gpu)

    plt.title("GPU Price Trends")
    plt.xlabel("Date")
    plt.ylabel("Price (€)")
    plt.legend()
    plt.grid()
    plt.show()

# Run the main function
if __name__ == "__main__":
    main()
